package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"io"
	"mime"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"
)

type LessonRecord struct {
	ID           int64        `json:"id"`
	ClassroomID  string       `json:"classroom_id"`
	Title        string       `json:"title"`
	Description  string       `json:"description"`
	HomeworkText string       `json:"homework_text"`
	DueAt        *int64       `json:"due_at,omitempty"`
	Position     int          `json:"position"`
	Published    bool         `json:"published"`
	CreatedAt    int64        `json:"created_at"`
	UpdatedAt    int64        `json:"updated_at"`
	Files        []LessonFile `json:"files"`
}

type LessonFile struct {
	ID           int64  `json:"id"`
	OriginalName string `json:"original_name"`
	MimeType     string `json:"mime_type"`
	SizeBytes    int64  `json:"size_bytes"`
}

type SubmissionRecord struct {
	ID          int64            `json:"id"`
	LessonID    int64            `json:"lesson_id"`
	StudentID   string           `json:"student_id"`
	StudentName string           `json:"student_name"`
	Note        string           `json:"note"`
	SubmittedAt int64            `json:"submitted_at"`
	UpdatedAt   int64            `json:"updated_at"`
	Files       []SubmissionFile `json:"files"`
}

type SubmissionFile struct {
	ID           int64  `json:"id"`
	OriginalName string `json:"original_name"`
	MimeType     string `json:"mime_type"`
	SizeBytes    int64  `json:"size_bytes"`
}

type lessonPayload struct {
	Title        string `json:"title"`
	Description  string `json:"description"`
	HomeworkText string `json:"homework_text"`
	DueAt        *int64 `json:"due_at"`
	Position     int    `json:"position"`
	Published    bool   `json:"published"`
}

func listLessons(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		role := requestAccessRole(r)

		query := `
SELECT id,classroom_id,title,description,homework_text,due_at,position,published,created_at,updated_at
FROM lessons WHERE classroom_id=?`
		args := []any{classroomID}
		if role != "teacher" {
			query += " AND published=1"
		}
		query += " ORDER BY position ASC, id ASC"

		rows, err := store.db.Query(query, args...)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer rows.Close()

		out := make([]LessonRecord, 0)
		for rows.Next() {
			var item LessonRecord
			var due sql.NullInt64
			var published int
			if err := rows.Scan(
				&item.ID, &item.ClassroomID, &item.Title, &item.Description,
				&item.HomeworkText, &due, &item.Position, &published,
				&item.CreatedAt, &item.UpdatedAt,
			); err != nil {
				http.Error(w, err.Error(), 500)
				return
			}
			item.Published = published != 0
			if due.Valid {
				v := due.Int64
				item.DueAt = &v
			}
			item.Files, _ = lessonFiles(store, item.ID)
			out = append(out, item)
		}
		writeJSON(w, out)
	}
}

func createLesson(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		var p lessonPayload
		if err := json.NewDecoder(r.Body).Decode(&p); err != nil {
			http.Error(w, "bad json", 400)
			return
		}
		p.Title = strings.TrimSpace(p.Title)
		if p.Title == "" {
			http.Error(w, "title is required", 400)
			return
		}
		now := time.Now().UTC().UnixMilli()
		var due any
		if p.DueAt != nil {
			due = *p.DueAt
		}
		res, err := store.db.Exec(`
INSERT INTO lessons(classroom_id,title,description,homework_text,due_at,position,published,created_at,updated_at)
VALUES(?,?,?,?,?,?,?,?,?)`,
			classroomID, p.Title, p.Description, p.HomeworkText, due,
			p.Position, boolInt(p.Published), now, now,
		)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		id, _ := res.LastInsertId()
		store.Audit(classroomID, "teacher-web", fmt.Sprint(id), "lesson_create")
		w.WriteHeader(http.StatusCreated)
		writeJSON(w, map[string]any{"id": id})
	}
}

func updateLesson(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		id, err := strconv.ParseInt(chi.URLParam(r, "lessonId"), 10, 64)
		if err != nil {
			http.Error(w, "bad lesson id", 400)
			return
		}
		var p lessonPayload
		if err := json.NewDecoder(r.Body).Decode(&p); err != nil {
			http.Error(w, "bad json", 400)
			return
		}
		p.Title = strings.TrimSpace(p.Title)
		if p.Title == "" {
			http.Error(w, "title is required", 400)
			return
		}
		var due any
		if p.DueAt != nil {
			due = *p.DueAt
		}
		res, err := store.db.Exec(`
UPDATE lessons
SET title=?,description=?,homework_text=?,due_at=?,position=?,published=?,updated_at=?
WHERE id=? AND classroom_id=?`,
			p.Title, p.Description, p.HomeworkText, due, p.Position,
			boolInt(p.Published), time.Now().UTC().UnixMilli(), id, classroomID,
		)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		n, _ := res.RowsAffected()
		if n == 0 {
			http.NotFound(w, r)
			return
		}
		store.Audit(classroomID, "teacher-web", fmt.Sprint(id), "lesson_update")
		writeJSON(w, map[string]any{"ok": true})
	}
}

func uploadLessonMaterial(store *Store, cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		lessonID, err := strconv.ParseInt(chi.URLParam(r, "lessonId"), 10, 64)
		if err != nil || !lessonBelongsToClassroom(store, lessonID, classroomID, true) {
			http.NotFound(w, r)
			return
		}

		file, header, err := readSingleUpload(w, r, cfg.MaxUploadMB, "file")
		if err != nil {
			http.Error(w, err.Error(), uploadStatus(err))
			return
		}
		defer file.Close()

		dir := filepath.Join(cfg.StorageDir, "lesson-materials", safe(classroomID), fmt.Sprint(lessonID))
		storedName, size, mimeType, err := saveUploadedFile(file, header, dir)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		res, err := store.db.Exec(`
INSERT INTO lesson_files(lesson_id,original_name,stored_name,mime_type,size_bytes,created_at)
VALUES(?,?,?,?,?,?)`,
			lessonID, cleanFilename(header.Filename), storedName, mimeType, size,
			time.Now().UTC().UnixMilli(),
		)
		if err != nil {
			_ = os.Remove(filepath.Join(dir, storedName))
			http.Error(w, err.Error(), 500)
			return
		}
		id, _ := res.LastInsertId()
		store.Audit(classroomID, "teacher-web", fmt.Sprint(lessonID), "lesson_material_upload")
		w.WriteHeader(http.StatusCreated)
		writeJSON(w, map[string]any{"id": id, "name": cleanFilename(header.Filename), "size": size})
	}
}

func downloadLessonMaterial(store *Store, cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		lessonID, err1 := strconv.ParseInt(chi.URLParam(r, "lessonId"), 10, 64)
		fileID, err2 := strconv.ParseInt(chi.URLParam(r, "fileId"), 10, 64)
		if err1 != nil || err2 != nil {
			http.NotFound(w, r)
			return
		}

		role := requestAccessRole(r)
		if !lessonBelongsToClassroom(store, lessonID, classroomID, role == "teacher") {
			http.NotFound(w, r)
			return
		}

		var original, stored, mimeType string
		err := store.db.QueryRow(`
SELECT original_name,stored_name,mime_type FROM lesson_files WHERE id=? AND lesson_id=?`,
			fileID, lessonID,
		).Scan(&original, &stored, &mimeType)
		if err != nil {
			http.NotFound(w, r)
			return
		}

		if mimeType != "" {
			w.Header().Set("Content-Type", mimeType)
		}
		w.Header().Set("Content-Disposition", mime.FormatMediaType("attachment", map[string]string{"filename": original}))
		http.ServeFile(w, r, filepath.Join(cfg.StorageDir, "lesson-materials", safe(classroomID), fmt.Sprint(lessonID), stored))
	}
}

func submitHomework(store *Store, cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		lessonID, err := strconv.ParseInt(chi.URLParam(r, "lessonId"), 10, 64)
		if err != nil || !lessonBelongsToClassroom(store, lessonID, classroomID, false) {
			http.NotFound(w, r)
			return
		}

		maxBytes := int64(cfg.MaxUploadMB) << 20
		r.Body = http.MaxBytesReader(w, r.Body, maxBytes+(1<<20))
		if err := r.ParseMultipartForm(maxBytes + (1 << 20)); err != nil {
			http.Error(w, fmt.Sprintf("upload exceeds %d MB or form is invalid", cfg.MaxUploadMB), http.StatusRequestEntityTooLarge)
			return
		}
		studentID := strings.TrimSpace(r.FormValue("student_id"))
		studentName := strings.TrimSpace(r.FormValue("student_name"))
		note := strings.TrimSpace(r.FormValue("note"))
		if studentID == "" || studentName == "" {
			http.Error(w, "student_id and student_name are required", 400)
			return
		}

		files := r.MultipartForm.File["files"]
		if len(files) == 0 {
			http.Error(w, "at least one file is required", 400)
			return
		}
		var total int64
		for _, h := range files {
			total += h.Size
		}
		if total > maxBytes {
			http.Error(w, fmt.Sprintf("total files exceed %d MB", cfg.MaxUploadMB), http.StatusRequestEntityTooLarge)
			return
		}

		now := time.Now().UTC().UnixMilli()
		res, err := store.db.Exec(`
INSERT INTO submissions(lesson_id,student_id,student_name,note,submitted_at,updated_at)
VALUES(?,?,?,?,?,?)`, lessonID, studentID, studentName, note, now, now)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		submissionID, _ := res.LastInsertId()
		dir := filepath.Join(cfg.StorageDir, "homework", safe(classroomID), fmt.Sprint(lessonID), fmt.Sprint(submissionID))
		if err := os.MkdirAll(dir, 0o755); err != nil {
			http.Error(w, err.Error(), 500)
			return
		}

		for _, header := range files {
			src, err := header.Open()
			if err != nil {
				continue
			}
			storedName, size, mimeType, saveErr := saveUploadedFile(src, header, dir)
			_ = src.Close()
			if saveErr != nil {
				continue
			}
			_, _ = store.db.Exec(`
INSERT INTO submission_files(submission_id,original_name,stored_name,mime_type,size_bytes,created_at)
VALUES(?,?,?,?,?,?)`,
				submissionID, cleanFilename(header.Filename), storedName, mimeType, size, now,
			)
		}

		store.Audit(classroomID, studentID, fmt.Sprint(lessonID), "homework_submit")
		w.WriteHeader(http.StatusCreated)
		writeJSON(w, map[string]any{"id": submissionID, "submitted_at": now})
	}
}

func listSubmissions(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		lessonID, err := strconv.ParseInt(chi.URLParam(r, "lessonId"), 10, 64)
		if err != nil || !lessonBelongsToClassroom(store, lessonID, classroomID, true) {
			http.NotFound(w, r)
			return
		}

		rows, err := store.db.Query(`
SELECT id,lesson_id,student_id,student_name,note,submitted_at,updated_at
FROM submissions WHERE lesson_id=? ORDER BY submitted_at DESC`, lessonID)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer rows.Close()

		out := make([]SubmissionRecord, 0)
		for rows.Next() {
			var item SubmissionRecord
			if err := rows.Scan(&item.ID, &item.LessonID, &item.StudentID, &item.StudentName,
				&item.Note, &item.SubmittedAt, &item.UpdatedAt); err != nil {
				http.Error(w, err.Error(), 500)
				return
			}
			item.Files, _ = submissionFiles(store, item.ID)
			out = append(out, item)
		}
		writeJSON(w, out)
	}
}

func listOwnSubmissions(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		lessonID, err := strconv.ParseInt(chi.URLParam(r, "lessonId"), 10, 64)
		if err != nil || !lessonBelongsToClassroom(store, lessonID, classroomID, false) {
			http.NotFound(w, r)
			return
		}
		studentID := strings.TrimSpace(r.URL.Query().Get("student_id"))
		if studentID == "" {
			http.Error(w, "student_id is required", 400)
			return
		}

		rows, err := store.db.Query(`
SELECT id,lesson_id,student_id,student_name,note,submitted_at,updated_at
FROM submissions WHERE lesson_id=? AND student_id=? ORDER BY submitted_at DESC`, lessonID, studentID)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer rows.Close()

		out := make([]SubmissionRecord, 0)
		for rows.Next() {
			var item SubmissionRecord
			if err := rows.Scan(&item.ID, &item.LessonID, &item.StudentID, &item.StudentName,
				&item.Note, &item.SubmittedAt, &item.UpdatedAt); err != nil {
				http.Error(w, err.Error(), 500)
				return
			}
			item.Files, _ = submissionFiles(store, item.ID)
			out = append(out, item)
		}
		writeJSON(w, out)
	}
}

func downloadSubmissionFile(store *Store, cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		classroomID := chi.URLParam(r, "lesson")
		lessonID, err1 := strconv.ParseInt(chi.URLParam(r, "lessonId"), 10, 64)
		submissionID, err2 := strconv.ParseInt(chi.URLParam(r, "submissionId"), 10, 64)
		fileID, err3 := strconv.ParseInt(chi.URLParam(r, "fileId"), 10, 64)
		if err1 != nil || err2 != nil || err3 != nil || !lessonBelongsToClassroom(store, lessonID, classroomID, true) {
			http.NotFound(w, r)
			return
		}

		var original, stored, mimeType string
		err := store.db.QueryRow(`
SELECT sf.original_name,sf.stored_name,sf.mime_type
FROM submission_files sf
JOIN submissions s ON s.id=sf.submission_id
WHERE sf.id=? AND sf.submission_id=? AND s.lesson_id=?`,
			fileID, submissionID, lessonID,
		).Scan(&original, &stored, &mimeType)
		if err != nil {
			http.NotFound(w, r)
			return
		}
		if mimeType != "" {
			w.Header().Set("Content-Type", mimeType)
		}
		w.Header().Set("Content-Disposition", mime.FormatMediaType("attachment", map[string]string{"filename": original}))
		http.ServeFile(w, r, filepath.Join(cfg.StorageDir, "homework", safe(classroomID), fmt.Sprint(lessonID), fmt.Sprint(submissionID), stored))
	}
}

func lessonFiles(store *Store, lessonID int64) ([]LessonFile, error) {
	rows, err := store.db.Query(`
SELECT id,original_name,mime_type,size_bytes FROM lesson_files WHERE lesson_id=? ORDER BY id ASC`, lessonID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := make([]LessonFile, 0)
	for rows.Next() {
		var f LessonFile
		if err := rows.Scan(&f.ID, &f.OriginalName, &f.MimeType, &f.SizeBytes); err != nil {
			return nil, err
		}
		out = append(out, f)
	}
	return out, rows.Err()
}

func submissionFiles(store *Store, submissionID int64) ([]SubmissionFile, error) {
	rows, err := store.db.Query(`
SELECT id,original_name,mime_type,size_bytes FROM submission_files WHERE submission_id=? ORDER BY id ASC`, submissionID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := make([]SubmissionFile, 0)
	for rows.Next() {
		var f SubmissionFile
		if err := rows.Scan(&f.ID, &f.OriginalName, &f.MimeType, &f.SizeBytes); err != nil {
			return nil, err
		}
		out = append(out, f)
	}
	return out, rows.Err()
}

func lessonBelongsToClassroom(store *Store, lessonID int64, includeDraft bool) bool {
	var published int
	err := store.db.QueryRow(`SELECT published FROM lessons WHERE id=?`, lessonID).Scan(&published)
	if err != nil {
		return false
	}
	return includeDraft || published != 0
}

func readSingleUpload(w http.ResponseWriter, r *http.Request, maxMB int, field string) (multipart.File, *multipart.FileHeader, error) {
	maxBytes := int64(maxMB) << 20
	r.Body = http.MaxBytesReader(w, r.Body, maxBytes+(1<<20))
	if err := r.ParseMultipartForm(maxBytes + (1 << 20)); err != nil {
		return nil, nil, fmt.Errorf("upload exceeds %d MB or form is invalid", maxMB)
	}
	file, header, err := r.FormFile(field)
	if err != nil {
		return nil, nil, err
	}
	if header.Size > maxBytes {
		_ = file.Close()
		return nil, nil, fmt.Errorf("file exceeds %d MB", maxMB)
	}
	return file, header, nil
}

func saveUploadedFile(src multipart.File, header *multipart.FileHeader, dir string) (stored string, size int64, mimeType string, err error) {
	if err = os.MkdirAll(dir, 0o755); err != nil {
		return
	}
	ext := strings.ToLower(filepath.Ext(cleanFilename(header.Filename)))
	if len(ext) > 16 {
		ext = ""
	}
	stored = randomSecret(12) + ext
	dstPath := filepath.Join(dir, stored)
	dst, createErr := os.Create(dstPath)
	if createErr != nil {
		err = createErr
		return
	}
	defer dst.Close()

	size, err = io.Copy(dst, src)
	if err != nil {
		_ = os.Remove(dstPath)
		return
	}
	mimeType = header.Header.Get("Content-Type")
	if mimeType == "" {
		mimeType = "application/octet-stream"
	}
	return
}

func cleanFilename(name string) string {
	name = filepath.Base(strings.ReplaceAll(name, "\\", "/"))
	name = strings.TrimSpace(name)
	if name == "" || name == "." {
		return "file"
	}
	return name
}

func uploadStatus(err error) int {
	if strings.Contains(strings.ToLower(err.Error()), "exceed") {
		return http.StatusRequestEntityTooLarge
	}
	return http.StatusBadRequest
}

func boolInt(v bool) int {
	if v {
		return 1
	}
	return 0
}

func writeJSON(w http.ResponseWriter, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	_ = json.NewEncoder(w).Encode(v)
}
