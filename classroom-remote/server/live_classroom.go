package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"mime"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"
)

type AttendanceRow struct {
	StudentID     string `json:"student_id"`
	StudentName   string `json:"student_name"`
	FirstSeen     int64  `json:"first_seen"`
	LastSeen      int64  `json:"last_seen"`
	OnlineMs      int64  `json:"online_ms"`
	Reconnects    int    `json:"reconnects"`
}

func attendanceReport(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		now := time.Now().UTC().UnixMilli()
		rows, err := store.db.Query(`
SELECT student_id,
       MAX(student_name),
       MIN(connected_at),
       MAX(COALESCE(disconnected_at, ?)),
       SUM(COALESCE(disconnected_at, ?) - connected_at),
       MAX(reconnect_no)
FROM attendance_sessions
WHERE classroom_id=?
GROUP BY student_id
ORDER BY MAX(student_name) COLLATE NOCASE`, now, now, classroomID)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer rows.Close()

		out := make([]AttendanceRow, 0)
		for rows.Next() {
			var v AttendanceRow
			if err := rows.Scan(&v.StudentID, &v.StudentName, &v.FirstSeen, &v.LastSeen, &v.OnlineMs, &v.Reconnects); err != nil {
				http.Error(w, err.Error(), 500)
				return
			}
			out = append(out, v)
		}
		writeJSON(w, out)
	}
}

type QuickFile struct {
	ID           int64  `json:"id"`
	OriginalName string `json:"original_name"`
	MimeType     string `json:"mime_type"`
	SizeBytes    int64  `json:"size_bytes"`
	CreatedAt    int64  `json:"created_at"`
}

func listQuickFiles(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		rows, err := store.db.Query(`
SELECT q.id,q.original_name,q.mime_type,q.size_bytes,q.created_at
FROM quick_files q
WHERE q.classroom_id=?
  AND NOT EXISTS (
      SELECT 1 FROM screenshot_tasks st WHERE st.file_id=q.id
  )
ORDER BY q.created_at DESC, q.id DESC`, classroomID)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer rows.Close()

		out := make([]QuickFile, 0)
		for rows.Next() {
			var f QuickFile
			if err := rows.Scan(&f.ID, &f.OriginalName, &f.MimeType, &f.SizeBytes, &f.CreatedAt); err != nil {
				http.Error(w, err.Error(), 500)
				return
			}
			out = append(out, f)
		}
		writeJSON(w, out)
	}
}

func uploadQuickFile(store *Store, cfg Config, hub *Hub) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		file, header, err := readSingleUpload(w, r, cfg.MaxUploadMB, "file")
		if r.MultipartForm != nil {
			defer r.MultipartForm.RemoveAll()
		}
		if err != nil {
			http.Error(w, err.Error(), uploadStatus(err))
			return
		}
		defer file.Close()

		dir := filepath.Join(cfg.StorageDir, "quick-files", safe(classroomID))
		storedName, size, mimeType, err := saveUploadedFile(file, header, dir)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		now := time.Now().UTC().UnixMilli()
		res, err := store.db.Exec(`
INSERT INTO quick_files(classroom_id,original_name,stored_name,mime_type,size_bytes,created_at)
VALUES(?,?,?,?,?,?)`,
			classroomID, cleanFilename(header.Filename), storedName, mimeType, size, now)
		if err != nil {
			_ = os.Remove(filepath.Join(dir, storedName))
			http.Error(w, err.Error(), 500)
			return
		}
		id, _ := res.LastInsertId()
		purpose := strings.TrimSpace(r.FormValue("purpose"))
		if purpose != "screenshot_task" {
			hub.sendLesson(classroomID, map[string]any{
				"type": "quick_file",
				"id": id,
				"name": cleanFilename(header.Filename),
				"size_bytes": size,
				"created_at": now,
			}, func(c *Client) bool { return c.Role == "student" })
		}
		w.WriteHeader(http.StatusCreated)
		writeJSON(w, map[string]any{"id": id, "name": cleanFilename(header.Filename), "size_bytes": size})
	}
}

func downloadQuickFile(store *Store, cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		fileID, err := strconv.ParseInt(chi.URLParam(r, "fileId"), 10, 64)
		if err != nil {
			http.NotFound(w, r)
			return
		}
		var original, stored, mimeType string
		err = store.db.QueryRow(`
SELECT original_name,stored_name,mime_type
FROM quick_files WHERE id=? AND classroom_id=?`, fileID, classroomID).
			Scan(&original, &stored, &mimeType)
		if err == sql.ErrNoRows {
			http.NotFound(w, r)
			return
		}
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		if mimeType != "" {
			w.Header().Set("Content-Type", mimeType)
		}
		w.Header().Set("Content-Disposition",
			mime.FormatMediaType("attachment", map[string]string{"filename": original}))
		http.ServeFile(w, r, filepath.Join(cfg.StorageDir, "quick-files", safe(classroomID), stored))
	}
}

func jsonMessageSizeOK(m map[string]any, max int) bool {
	b, _ := json.Marshal(m)
	return len(b) <= max
}

func sanitizeChatText(v any) string {
	s, _ := v.(string)
	s = strings.TrimSpace(s)
	if len([]rune(s)) > 2000 {
		s = string([]rune(s)[:2000])
	}
	return s
}

func safePollOptions(v any) []string {
	raw, ok := v.([]any)
	if !ok {
		return nil
	}
	out := make([]string, 0, len(raw))
	for _, item := range raw {
		s := strings.TrimSpace(fmt.Sprint(item))
		if s == "" {
			continue
		}
		if len([]rune(s)) > 200 {
			s = string([]rune(s)[:200])
		}
		out = append(out, s)
		if len(out) >= 10 {
			break
		}
	}
	return out
}


type ScreenshotTask struct {
	ID           int64  `json:"id"`
	FileID       int64  `json:"file_id"`
	Title        string `json:"title"`
	Text         string `json:"text"`
	OriginalName string `json:"name"`
	CreatedAt    int64  `json:"created_at"`
}

func listScreenshotTasks(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		rows, err := store.db.Query(`
SELECT id,file_id,title,task_text,original_name,created_at
FROM screenshot_tasks
WHERE classroom_id=?
ORDER BY created_at DESC, id DESC
LIMIT 500`, classroomID)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer rows.Close()

		out := make([]ScreenshotTask, 0)
		for rows.Next() {
			var item ScreenshotTask
			if err := rows.Scan(&item.ID, &item.FileID, &item.Title, &item.Text,
				&item.OriginalName, &item.CreatedAt); err != nil {
				http.Error(w, err.Error(), 500)
				return
			}
			out = append(out, item)
		}
		writeJSON(w, out)
	}
}
