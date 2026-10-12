package main

import (
	"crypto/subtle"
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
}

type Hub struct {
	mu      sync.RWMutex
	clients map[*Client]bool
}

func NewHub() *Hub {
	return &Hub{clients: map[*Client]bool{}}
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
		ID   string `json:"id"`
		Name string `json:"name"`
		Role string `json:"role"`
	}
	var users []item
	h.mu.RLock()
	for c := range h.clients {
		if c.LessonID == lessonID {
			users = append(users, item{c.ID, c.Name, c.Role})
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

func (a authConfig) valid(role, token string) bool {
	expected := ""
	switch role {
	case "teacher":
		expected = a.teacherToken
	case "student":
		expected = a.studentToken
	default:
		return false
	}
	if expected == "" || token == "" || len(expected) != len(token) {
		return false
	}
	return subtle.ConstantTimeCompare([]byte(expected), []byte(token)) == 1
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

func wsHandler(h *Hub, rtcRelay *RTCRelay, auth authConfig) http.HandlerFunc {
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
		if !auth.valid(role, token) {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}

		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			return
		}
		c := &Client{ID: id, Role: role, LessonID: lessonID, Name: name, Conn: conn, Send: make(chan []byte, 64)}
		h.add(c)
		defer func() { rtcRelay.CloseClient(c.ID); h.remove(c); conn.Close() }()

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
					m["student_id"] = c.ID
					m["student_name"] = c.Name
					h.sendLesson(lessonID, m, func(x *Client) bool { return x.Role == "teacher" })
				}
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
			case "signal":
				target, _ := m["target"].(string)
				if target != "" {
					m["from"] = c.ID
					m["from_role"] = c.Role
					h.sendTo(lessonID, target, m)
				}
			case "remote_request", "remote_stop", "audio_request", "audio_stop":
				if c.Role != "teacher" {
					continue
				}
				target, _ := m["target"].(string)
				if target != "" {
					m["from"] = c.ID
					m["from_role"] = c.Role
					h.sendTo(lessonID, target, m)
				}
			case "lecture_state":
				if c.Role == "teacher" {
					h.sendLesson(lessonID, m, func(x *Client) bool { return x.Role == "student" })
				}
			}
		}
	}
}

func snapshotUpload(root string) http.HandlerFunc {
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
	addr := env("ADDR", ":8080")
	root := env("STORAGE_DIR", "./storage")
	auth := authConfig{
		teacherToken: strings.TrimSpace(os.Getenv("TEACHER_TOKEN")),
		studentToken: strings.TrimSpace(os.Getenv("STUDENT_TOKEN")),
	}
	if auth.teacherToken == "" || auth.studentToken == "" {
		log.Fatal("TEACHER_TOKEN and STUDENT_TOKEN must be configured")
	}
	hub := NewHub()
	rtcRelay, rtcErr := NewRTCRelay()
	if rtcErr != nil {
		log.Fatal(rtcErr)
	}
	r := chi.NewRouter()
	r.Get("/health", func(w http.ResponseWriter, r *http.Request) { w.Write([]byte("ok")) })
	r.Get("/ws", wsHandler(hub, rtcRelay, auth))
	r.Post("/api/v1/lessons/{lesson}/students/{student}/snapshot",
		requireBearer(auth.studentToken, snapshotUpload(root)))
	r.Get("/api/v1/lessons/{lesson}/students/{student}/snapshot/latest",
		requireBearer(auth.teacherToken, latestSnapshot(root)))
	r.Get("/api/v1/lessons/{lesson}/students/{student}/recording.zip",
		requireBearer(auth.teacherToken, recordingZip(root)))
	r.Get("/api/v1/lessons/{lesson}/students/{student}/recording.mp4",
		requireBearer(auth.teacherToken, recordingMP4(root)))
	log.Printf("classroom server listening on %s", addr)
	log.Fatal(http.ListenAndServe(addr, r))
}

func env(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}
