package main

import (
	"context"
	"crypto/subtle"
	"flag"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/go-chi/chi/v5"
	"github.com/gorilla/websocket"
)

type Client struct {
	ID       string
	Role     string
	LessonID string
	Name     string
	Conn     *websocket.Conn
	Send     chan []byte

	Status       string
	RTCReady     bool
	Watching     bool
	HomeworkBusy bool
	HandRaised   bool
	Confused     bool
	AttendanceID int64
}

type Hub struct {
	mu          sync.RWMutex
	clients     map[*Client]bool
	chatEnabled map[string]bool
	pollAnswers map[string]map[string]bool
}

type onlineClient struct {
	ID       string `json:"id"`
	Name     string `json:"name"`
	Role     string `json:"role"`
	LessonID string `json:"lesson_id"`
}

func (h *Hub) snapshot() []onlineClient {
	h.mu.RLock()
	defer h.mu.RUnlock()
	out := make([]onlineClient, 0, len(h.clients))
	for c := range h.clients {
		out = append(out, onlineClient{ID: c.ID, Name: c.Name, Role: c.Role, LessonID: c.LessonID})
	}
	return out
}

func NewHub() *Hub {
	return &Hub{
		clients:     map[*Client]bool{},
		chatEnabled: map[string]bool{},
		pollAnswers: map[string]map[string]bool{},
	}
}

func (h *Hub) add(c *Client) {
	h.mu.Lock()
	h.clients[c] = true
	h.mu.Unlock()
	h.broadcastPresence(c.LessonID)
}

func (h *Hub) remove(c *Client) {
	h.mu.Lock()
	delete(h.clients, c)
	h.mu.Unlock()
	close(c.Send)
	h.broadcastPresence(c.LessonID)
}

func (h *Hub) sendLesson(lessonID string, payload any, filter func(*Client) bool) {
	b, _ := json.Marshal(payload)
	h.mu.RLock()
	defer h.mu.RUnlock()
	for c := range h.clients {
		if c.LessonID == lessonID && (filter == nil || filter(c)) {
			select {
			case c.Send <- b:
			default:
			}
		}
	}
}

func (h *Hub) sendTo(lessonID, clientID string, payload any) {
	h.sendLesson(lessonID, payload, func(c *Client) bool { return c.ID == clientID })
}

func (h *Hub) broadcastPresence(lessonID string) {
	type item struct {
		ID           string `json:"id"`
		Name         string `json:"name"`
		Role         string `json:"role"`
		Status       string `json:"status,omitempty"`
		RTCReady     bool   `json:"rtc_ready,omitempty"`
		Watching     bool   `json:"watching,omitempty"`
		HomeworkBusy bool   `json:"homework_busy,omitempty"`
		HandRaised   bool   `json:"hand_raised,omitempty"`
		Confused     bool   `json:"confused,omitempty"`
	}
	var users []item
	h.mu.RLock()
	for c := range h.clients {
		if c.LessonID == lessonID {
			users = append(users, item{
				ID: c.ID, Name: c.Name, Role: c.Role,
				Status: c.Status, RTCReady: c.RTCReady, Watching: c.Watching,
				HomeworkBusy: c.HomeworkBusy, HandRaised: c.HandRaised, Confused: c.Confused,
			})
		}
	}
	h.mu.RUnlock()
	h.sendLesson(lessonID, map[string]any{"type": "presence", "users": users},
		func(c *Client) bool { return c.Role == "teacher" })
}

var upgrader = websocket.Upgrader{CheckOrigin: func(r *http.Request) bool { return true }}

type authConfig struct {
	teacherToken string
	studentToken string
}

func (a authConfig) valid(role, lessonID, token string, store *Store) bool {
	expected := ""
	switch role {
	case "teacher":
		expected = a.teacherToken
	case "student":
		expected = a.studentToken
	default:
		return false
	}

	if expected != "" && token != "" && len(expected) == len(token) &&
		subtle.ConstantTimeCompare([]byte(expected), []byte(token)) == 1 {
		return true
	}
	return store != nil && store.ValidateInvitation(token, role, lessonID)
}

func bearerToken(r *http.Request) string {
	const prefix = "Bearer "
	v := r.Header.Get("Authorization")
	if !strings.HasPrefix(v, prefix) {
		return ""
	}
	return strings.TrimSpace(strings.TrimPrefix(v, prefix))
}

func requireBearer(expected string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		token := bearerToken(r)
		if expected == "" || token == "" || len(expected) != len(token) ||
			subtle.ConstantTimeCompare([]byte(expected), []byte(token)) != 1 {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		next(w, r)
	}
}

func requireRoleAccess(auth authConfig, store *Store, role string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		lessonID := chi.URLParam(r, "lesson")
		if !auth.valid(role, lessonID, bearerToken(r), store) {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		next(w, r)
	}
}

type accessRoleKey struct{}

func requireAnyRoleAccess(auth authConfig, store *Store, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		token := bearerToken(r)
		role := ""
		if auth.valid("teacher", classroomID, token, store) {
			role = "teacher"
		} else if auth.valid("student", classroomID, token, store) {
			role = "student"
		} else {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		ctx := context.WithValue(r.Context(), accessRoleKey{}, role)
		next(w, r.WithContext(ctx))
	}
}

func requestAccessRole(r *http.Request) string {
	if v, ok := r.Context().Value(accessRoleKey{}).(string); ok {
		return v
	}
	return ""
}

func wsHandler(h *Hub, rtcRelay *RTCRelay, auth authConfig, iceURLs []string, store *Store, maxUploadMB int) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		lessonID := r.URL.Query().Get("lesson")
		id := r.URL.Query().Get("id")
		role := r.URL.Query().Get("role")
		name := r.URL.Query().Get("name")
		token := bearerToken(r)
		if lessonID == "" || id == "" || (role != "teacher" && role != "student") {
			http.Error(w, "lesson, id and role are required", http.StatusBadRequest)
			return
		}
		if !auth.valid(role, lessonID, token, store) {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}

		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			return
		}
		c := &Client{
			ID: id, Role: role, LessonID: lessonID, Name: name,
			Conn: conn, Send: make(chan []byte, 64), Status: "online",
		}
		if role == "student" {
			c.AttendanceID = store.AttendanceConnect(lessonID, id, name)
		}
		h.add(c)
		if c.Role == "teacher" {
			h.mu.Lock()
			if _, ok := h.chatEnabled[lessonID]; !ok {
				h.chatEnabled[lessonID] = true
			}
			h.mu.Unlock()
		}
		h.sendTo(lessonID, c.ID, map[string]any{
			"type":          "server_config",
			"ice_servers":   iceURLs,
			"max_upload_mb": maxUploadMB,
		})
		store.Audit(lessonID, c.ID, "", "connect")
		defer func() {
			if c.Role == "student" {
				store.AttendanceDisconnect(c.AttendanceID)
			}
			store.Audit(lessonID, c.ID, "", "disconnect")
			rtcRelay.CloseClient(c.ID)
			h.remove(c)
			_ = conn.Close()
		}()

		go func() {
			for msg := range c.Send {
				_ = conn.WriteMessage(websocket.TextMessage, msg)
			}
		}()

		for {
			_, b, err := conn.ReadMessage()
			if err != nil {
				return
			}
			var m map[string]any
			if json.Unmarshal(b, &m) != nil {
				continue
			}
			t, _ := m["type"].(string)
			switch t {
			case "hand_raise", "hand_down":
				if c.Role == "student" {
					h.mu.Lock()
					c.HandRaised = (t == "hand_raise")
					h.mu.Unlock()
					m["student_id"] = c.ID
					m["student_name"] = c.Name
					h.sendLesson(lessonID, m, func(x *Client) bool { return x.Role == "teacher" })
					h.broadcastPresence(lessonID)
				}

			case "student_confused":
				if c.Role != "student" || !jsonMessageSizeOK(m, 4*1024) {
					continue
				}
				active, _ := m["active"].(bool)
				h.mu.Lock()
				c.Confused = active
				h.mu.Unlock()
				h.sendLesson(lessonID, map[string]any{
					"type": "student_confused",
					"active": active,
					"student_id": c.ID,
					"student_name": c.Name,
				}, func(x *Client) bool { return x.Role == "teacher" })
				h.broadcastPresence(lessonID)
			case "rtc_offer":
				sdp, _ := m["sdp"].(string)
				if sdp == "" {
					continue
				}
				answer, rtcErr := rtcRelay.AcceptOffer(c, sdp)
				if rtcErr != nil {
					h.sendTo(lessonID, c.ID, map[string]any{"type": "rtc_error", "message": rtcErr.Error()})
					continue
				}
				h.sendTo(lessonID, c.ID, map[string]any{"type": "rtc_answer", "sdp": answer})
				if c.Role == "student" {
					h.mu.Lock()
					c.RTCReady = true
					h.mu.Unlock()
					h.broadcastPresence(lessonID)
				}
				if c.Role == "student" {
					h.sendLesson(lessonID,
						map[string]any{"type": "keyframe_request", "track": trackLectureVideo},
						func(x *Client) bool { return x.Role == "teacher" })
				}
			case "signal":
				target, _ := m["target"].(string)
				if target != "" {
					m["from"] = c.ID
					m["from_role"] = c.Role
					h.sendTo(lessonID, target, m)
				}
			case "remote_request":
				if c.Role != "teacher" {
					continue
				}
				target, _ := m["target"].(string)
				if target != "" {
					store.Audit(lessonID, c.ID, target, "remote_request")
					rtcRelay.SetRemoteTarget(c.ID, target)
					m["from"] = c.ID
					m["from_role"] = c.Role
					h.sendTo(lessonID, target, m)
				}
			case "remote_stop":
				if c.Role != "teacher" {
					continue
				}
				rtcRelay.ClearRemoteTarget(c.ID)
				target, _ := m["target"].(string)
				store.Audit(lessonID, c.ID, target, "remote_stop")
				if target != "" {
					m["from"] = c.ID
					m["from_role"] = c.Role
					h.sendTo(lessonID, target, m)
				}
			case "audio_request":
				if c.Role != "teacher" {
					continue
				}
				target, _ := m["target"].(string)
				if target != "" {
					store.Audit(lessonID, c.ID, target, "audio_request")
					rtcRelay.SetAudioTarget(c.ID, target)
					m["from"] = c.ID
					m["from_role"] = c.Role
					h.sendTo(lessonID, target, m)
				}
			case "audio_stop":
				if c.Role != "teacher" {
					continue
				}
				rtcRelay.ClearAudioTarget(c.ID)
				target, _ := m["target"].(string)
				store.Audit(lessonID, c.ID, target, "audio_stop")
				if target != "" {
					m["from"] = c.ID
					m["from_role"] = c.Role
					h.sendTo(lessonID, target, m)
				}
			case "student_status":
				if c.Role != "student" {
					continue
				}
				status, _ := m["status"].(string)
				if status == "" {
					status = "online"
				}
				watching, _ := m["watching"].(bool)
				homework, _ := m["homework_busy"].(bool)
				rtcReady, hasRTC := m["rtc_ready"].(bool)
				h.mu.Lock()
				c.Status = status
				c.Watching = watching
				c.HomeworkBusy = homework
				if hasRTC {
					c.RTCReady = rtcReady
				}
				h.mu.Unlock()
				h.broadcastPresence(lessonID)

			case "annotation", "annotation_clear":
				if c.Role != "teacher" || !jsonMessageSizeOK(m, 64*1024) {
					continue
				}
				h.sendLesson(lessonID, m, func(x *Client) bool { return x.Role == "student" })

			case "attention":
				if c.Role != "teacher" || !jsonMessageSizeOK(m, 8*1024) {
					continue
				}
				text := strings.TrimSpace(fmt.Sprint(m["text"]))
				if len([]rune(text)) > 500 {
					text = string([]rune(text)[:500])
				}
				active, _ := m["active"].(bool)
				h.sendLesson(lessonID, map[string]any{
					"type": "attention", "active": active, "text": text,
				}, func(x *Client) bool { return x.Role == "student" })

			case "task_timer":
				if c.Role != "teacher" || !jsonMessageSizeOK(m, 4*1024) {
					continue
				}
				active, _ := m["active"].(bool)
				seconds := 0
				if v, ok := m["seconds"].(float64); ok {
					seconds = int(v)
				}
				if seconds < 0 {
					seconds = 0
				}
				if seconds > 6*60*60 {
					seconds = 6 * 60 * 60
				}
				endAt := time.Now().UTC().UnixMilli() + int64(seconds)*1000
				h.sendLesson(lessonID, map[string]any{
					"type": "task_timer",
					"active": active,
					"seconds": seconds,
					"end_at": endAt,
				}, func(x *Client) bool { return x.Role == "student" })

			case "poll_start":
				if c.Role != "teacher" {
					continue
				}
				pollID := strings.TrimSpace(fmt.Sprint(m["poll_id"]))
				question := strings.TrimSpace(fmt.Sprint(m["question"]))
				options := safePollOptions(m["options"])
				if pollID == "" || question == "" || len(options) < 2 {
					continue
				}
				if len([]rune(question)) > 500 {
					question = string([]rune(question)[:500])
				}
				h.mu.Lock()
				h.pollAnswers[lessonID+"|"+pollID] = map[string]bool{}
				h.mu.Unlock()
				fullscreen, _ := m["fullscreen"].(bool)
				h.sendLesson(lessonID, map[string]any{
					"type": "poll_start", "poll_id": pollID,
					"question": question, "options": options,
					"fullscreen": fullscreen,
				}, func(x *Client) bool { return x.Role == "student" })

			case "poll_close":
				if c.Role != "teacher" {
					continue
				}
				pollID := strings.TrimSpace(fmt.Sprint(m["poll_id"]))
				if pollID == "" {
					continue
				}
				h.mu.Lock()
				delete(h.pollAnswers, lessonID+"|"+pollID)
				h.mu.Unlock()
				h.sendLesson(lessonID, map[string]any{
					"type": "poll_close",
					"poll_id": pollID,
				}, func(x *Client) bool { return x.Role == "student" })

			case "poll_results":
				if c.Role != "teacher" || !jsonMessageSizeOK(m, 32*1024) {
					continue
				}
				pollID := strings.TrimSpace(fmt.Sprint(m["poll_id"]))
				question := strings.TrimSpace(fmt.Sprint(m["question"]))
				if pollID == "" || question == "" {
					continue
				}
				h.sendLesson(lessonID, m, func(x *Client) bool { return x.Role == "student" })

			case "poll_answer":
				if c.Role != "student" {
					continue
				}
				pollID := strings.TrimSpace(fmt.Sprint(m["poll_id"]))
				answer := int(-1)
				if v, ok := m["answer"].(float64); ok {
					answer = int(v)
				}
				key := lessonID + "|" + pollID
				h.mu.Lock()
				seen := h.pollAnswers[key]
				if seen == nil {
					h.mu.Unlock()
					continue
				}
				if seen[c.ID] {
					h.mu.Unlock()
					continue
				}
				seen[c.ID] = true
				h.mu.Unlock()
				h.sendLesson(lessonID, map[string]any{
					"type": "poll_answer", "poll_id": pollID, "answer": answer,
					"student_id": c.ID, "student_name": c.Name,
				}, func(x *Client) bool { return x.Role == "teacher" })

			case "chat_control":
				if c.Role != "teacher" {
					continue
				}
				enabled, _ := m["enabled"].(bool)
				h.mu.Lock()
				h.chatEnabled[lessonID] = enabled
				h.mu.Unlock()
				h.sendLesson(lessonID, map[string]any{
					"type": "chat_control", "enabled": enabled,
				}, nil)

			case "chat_message":
				text := sanitizeChatText(m["text"])
				if text == "" {
					continue
				}
				target, _ := m["target"].(string)
				if c.Role == "student" && target == "" {
					h.mu.RLock()
					enabled := h.chatEnabled[lessonID]
					h.mu.RUnlock()
					if !enabled {
						continue
					}
				}
				msg := map[string]any{
					"type": "chat_message", "text": text,
					"from": c.ID, "from_name": c.Name, "from_role": c.Role,
					"target": target, "created_at": time.Now().UTC().UnixMilli(),
				}
				if target == "teacher" && c.Role == "student" {
					h.sendLesson(lessonID, msg, func(x *Client) bool {
						return x.Role == "teacher" || x.ID == c.ID
					})
				} else if target != "" {
					h.sendTo(lessonID, target, msg)
					h.sendTo(lessonID, c.ID, msg)
				} else {
					h.sendLesson(lessonID, msg, nil)
				}

			case "screenshot_task":
				if c.Role != "teacher" || !jsonMessageSizeOK(m, 16*1024) {
					continue
				}
				fileID, _ := m["file_id"].(float64)
				text := strings.TrimSpace(fmt.Sprint(m["text"]))
				title := strings.TrimSpace(fmt.Sprint(m["title"]))
				name := strings.TrimSpace(fmt.Sprint(m["name"]))
				if fileID <= 0 {
					continue
				}
				if len([]rune(text)) > 3000 {
					text = string([]rune(text)[:3000])
				}
				if len([]rune(title)) > 200 {
					title = string([]rune(title)[:200])
				}
				now := time.Now().UTC().UnixMilli()
				res, err := store.db.Exec(`
INSERT INTO screenshot_tasks(classroom_id,file_id,title,task_text,original_name,created_at)
VALUES(?,?,?,?,?,?)`,
					lessonID, int64(fileID), title, text, name, now)
				if err != nil {
					log.Printf("save screenshot task: %v", err)
					continue
				}
				taskID, _ := res.LastInsertId()
				h.sendLesson(lessonID, map[string]any{
					"type": "screenshot_task",
					"id": taskID,
					"file_id": fileID,
					"text": text,
					"title": title,
					"name": name,
					"created_at": now,
				}, func(x *Client) bool { return x.Role == "student" })

			case "lecture_state":
				if c.Role == "teacher" {
					h.sendLesson(lessonID, m, func(x *Client) bool { return x.Role == "student" })
				}
			}
		}
	}
}

func snapshotUpload(root string, store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		lesson := chi.URLParam(r, "lesson")
		student := chi.URLParam(r, "student")
		if lesson == "" || student == "" {
			http.Error(w, "bad path", 400)
			return
		}
		maxBytes := int64(2 << 20)
		r.Body = http.MaxBytesReader(w, r.Body, maxBytes)
		defer r.Body.Close()

		dir := filepath.Join(root, safe(lesson), safe(student))
		if err := os.MkdirAll(dir, 0o755); err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		ts := time.Now().UTC().UnixMilli()
		ext := ".jpg"
		contentType := strings.ToLower(r.Header.Get("Content-Type"))
		if strings.Contains(contentType, "webp") {
			ext = ".webp"
		}
		name := fmt.Sprintf("%d%s", ts, ext)
		dst, err := os.Create(filepath.Join(dir, name))
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer dst.Close()
		if _, err = io.Copy(dst, r.Body); err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		store.AddSnapshot(lesson, student, name, ts)
		_ = json.NewEncoder(w).Encode(map[string]any{"ok": true, "timestamp": ts, "file": name})
	}
}

func latestSnapshot(root string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		dir := filepath.Join(root, safe(chi.URLParam(r, "lesson")), safe(chi.URLParam(r, "student")))
		entries, err := os.ReadDir(dir)
		if err != nil || len(entries) == 0 {
			http.NotFound(w, r)
			return
		}
		var newest os.DirEntry
		var newestTS int64
		for _, e := range entries {
			if e.IsDir() || (!strings.HasSuffix(e.Name(), ".webp") && !strings.HasSuffix(e.Name(), ".jpg") && !strings.HasSuffix(e.Name(), ".jpeg")) {
				continue
			}
			base := strings.TrimSuffix(strings.TrimSuffix(strings.TrimSuffix(e.Name(), ".webp"), ".jpg"), ".jpeg")
			v, _ := strconv.ParseInt(base, 10, 64)
			if v > newestTS {
				newestTS, newest = v, e
			}
		}
		if newest == nil {
			http.NotFound(w, r)
			return
		}
		if strings.HasSuffix(newest.Name(), ".webp") {
			w.Header().Set("Content-Type", "image/webp")
		} else {
			w.Header().Set("Content-Type", "image/jpeg")
		}
		http.ServeFile(w, r, filepath.Join(dir, newest.Name()))
	}
}

func safe(s string) string {
	s = filepath.Base(s)
	s = strings.ReplaceAll(s, "..", "_")
	return s
}

func main() {
	configPath := flag.String("config", "config.json", "path to server config")
	flag.Parse()

	cfg, created, err := loadConfig(*configPath)
	if err != nil {
		log.Fatal(err)
	}
	if cfg.GeneratedAdminPassword != "" {
		log.Printf("ADMIN_PASSWORD=%s", cfg.GeneratedAdminPassword)
		log.Printf("save this password now; only its bcrypt hash is stored in config.json")
	}
	if created {
		log.Printf("created %s", *configPath)
		log.Printf("TEACHER_PIN=%s", cfg.TeacherPIN)
		log.Printf("STUDENT_PIN=%s", cfg.StudentPIN)
		log.Printf("edit public_ip/domain and PINs in config before Internet deployment")
	}

	store, err := OpenStore(cfg.DBPath)
	if err != nil {
		log.Fatal(err)
	}
	defer store.Close()

	auth := authConfig{
		teacherToken: cfg.TeacherToken,
		studentToken: cfg.StudentToken,
	}

	turnServer, err := StartEmbeddedTURN(cfg)
	if err != nil {
		log.Fatal(err)
	}
	if turnServer != nil {
		defer turnServer.Close()
		log.Printf("embedded TURN/STUN listening on %s, relay ports %d-%d",
			cfg.TURNListen, cfg.TURNMinPort, cfg.TURNMaxPort)
	}

	iceURLs := []string{}
	if turnServer != nil {
		iceURLs = append(iceURLs, turnServer.urls...)
	} else if strings.TrimSpace(cfg.Domain) != "" || strings.TrimSpace(cfg.PublicIP) != "" {
		// Public deployment without embedded TURN may still use a public STUN server.
		iceURLs = append(iceURLs, "stun://stun.l.google.com:19302")
	}
	// For a pure LAN deployment keep iceURLs empty. WebRTC will use host
	// candidates directly and therefore has no Internet dependency.

	hub := NewHub()
	rtcRelay, err := NewRTCRelay(cfg)
	if err != nil {
		log.Fatal(err)
	}

	r := chi.NewRouter()
	pinAuthLimiter := newPINLimiter()
	pinManager := newAdminPINManager(&cfg, *configPath)
	r.Get("/health", func(w http.ResponseWriter, r *http.Request) { _, _ = w.Write([]byte("ok")) })
	r.Post("/api/client/login", pinLogin(cfg, pinAuthLimiter, pinManager))
	r.Get("/ws", wsHandler(hub, rtcRelay, auth, iceURLs, store, cfg.MaxUploadMB))

	r.Get("/api/admin/session", adminSession(cfg))
	r.Post("/api/admin/login", adminLogin(cfg))
	r.Post("/api/admin/logout", adminLogout(cfg))
	r.Get("/api/admin/access-pins", requireAdmin(cfg, pinManager.getHandler()))
	r.Post("/api/admin/access-pins/regenerate", requireAdmin(cfg, pinManager.regenerateHandler()))
	r.Post("/api/admin/invites",
		requireAdmin(cfg, createInvitation(store, cfg)))
	r.Get("/api/admin/status",
		requireAdmin(cfg, adminStatus(hub, cfg, turnServer)))
	r.Get("/api/admin/classrooms/{lesson}/attendance",
		requireAdmin(cfg, attendanceReport(store)))

	r.Get("/api/admin/classrooms/{lesson}/lessons",
		requireAdminAsTeacher(cfg, listLessons(store)))
	r.Post("/api/admin/classrooms/{lesson}/lessons",
		requireAdminAsTeacher(cfg, createLesson(store)))
	r.Put("/api/admin/classrooms/{lesson}/lessons/{lessonId}",
		requireAdminAsTeacher(cfg, updateLesson(store)))
	r.Post("/api/admin/classrooms/{lesson}/lessons/{lessonId}/materials",
		requireAdminAsTeacher(cfg, uploadLessonMaterial(store, cfg)))
	r.Get("/api/admin/classrooms/{lesson}/lessons/{lessonId}/materials/{fileId}",
		requireAdminAsTeacher(cfg, downloadLessonMaterial(store, cfg)))
	r.Get("/api/admin/classrooms/{lesson}/lessons/{lessonId}/submissions",
		requireAdminAsTeacher(cfg, listSubmissions(store)))
	r.Get("/api/admin/classrooms/{lesson}/lessons/{lessonId}/submissions/{submissionId}/files/{fileId}",
		requireAdminAsTeacher(cfg, downloadSubmissionFile(store, cfg)))

	r.Get("/api/invite/{code}", resolveInvitation(store, cfg))
	r.Get("/join/{code}", joinInvitationPage(store))

	r.Get("/api/v1/classrooms/{lesson}/lessons",
		requireAnyRoleAccess(auth, store, listLessons(store)))
	r.Post("/api/v1/classrooms/{lesson}/lessons",
		requireRoleAccess(auth, store, "teacher", createLesson(store)))
	r.Put("/api/v1/classrooms/{lesson}/lessons/{lessonId}",
		requireRoleAccess(auth, store, "teacher", updateLesson(store)))
	r.Post("/api/v1/classrooms/{lesson}/lessons/{lessonId}/materials",
		requireRoleAccess(auth, store, "teacher", uploadLessonMaterial(store, cfg)))
	r.Get("/api/v1/classrooms/{lesson}/lessons/{lessonId}/materials/{fileId}",
		requireAnyRoleAccess(auth, store, downloadLessonMaterial(store, cfg)))
	r.Post("/api/v1/classrooms/{lesson}/lessons/{lessonId}/submissions",
		requireRoleAccess(auth, store, "student", submitHomework(store, cfg)))
	r.Get("/api/v1/classrooms/{lesson}/lessons/{lessonId}/submissions",
		requireRoleAccess(auth, store, "teacher", listSubmissions(store)))
	r.Get("/api/v1/classrooms/{lesson}/lessons/{lessonId}/my-submissions",
		requireRoleAccess(auth, store, "student", listOwnSubmissions(store)))
	r.Get("/api/v1/classrooms/{lesson}/lessons/{lessonId}/submissions/{submissionId}/files/{fileId}",
		requireRoleAccess(auth, store, "teacher", downloadSubmissionFile(store, cfg)))

	r.Get("/api/v1/classrooms/{lesson}/quick-files",
		requireAnyRoleAccess(auth, store, listQuickFiles(store)))
	r.Post("/api/v1/classrooms/{lesson}/quick-files",
		requireRoleAccess(auth, store, "teacher", uploadQuickFile(store, cfg, hub)))
	r.Get("/api/v1/classrooms/{lesson}/quick-files/{fileId}",
		requireAnyRoleAccess(auth, store, downloadQuickFile(store, cfg)))
	r.Get("/api/v1/classrooms/{lesson}/screenshot-tasks",
		requireRoleAccess(auth, store, "student", listScreenshotTasks(store)))
	r.Get("/api/v1/classrooms/{lesson}/attendance",
		requireRoleAccess(auth, store, "teacher", attendanceReport(store)))

	r.Post("/api/v1/lessons/{lesson}/students/{student}/snapshot",
		requireRoleAccess(auth, store, "student", snapshotUpload(cfg.StorageDir, store)))
	r.Get("/api/v1/lessons/{lesson}/students/{student}/snapshot/latest",
		requireRoleAccess(auth, store, "teacher", latestSnapshot(cfg.StorageDir)))
	r.Get("/api/v1/lessons/{lesson}/students/{student}/recording.zip",
		requireRoleAccess(auth, store, "teacher", recordingZip(cfg.StorageDir)))
	r.Get("/api/v1/lessons/{lesson}/students/{student}/recording.mp4",
		requireRoleAccess(auth, store, "teacher", recordingMP4(cfg.StorageDir, cfg.FFmpegPath)))

	r.Handle("/*", webHandler())
	r.Handle("/", webHandler())

	log.Printf("SQLite: %s", cfg.DBPath)
	log.Fatal(serveApplication(cfg, r))
}

func env(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}
