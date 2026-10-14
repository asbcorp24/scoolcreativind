package main

import (
	"context"
	"encoding/json"
	"net/http/httptest"
	"path/filepath"
	"testing"
	"time"

	"github.com/go-chi/chi/v5"
)

func requestWithClassroom(path, classroomID, role string) *http.Request {
	req := httptest.NewRequest("GET", path, nil)
	rctx := chi.NewRouteContext()
	rctx.URLParams.Add("lesson", classroomID)
	ctx := context.WithValue(req.Context(), chi.RouteCtxKey, rctx)
	ctx = context.WithValue(ctx, accessRoleKey{}, role)
	return req.WithContext(ctx)
}

func TestLessonVisibilityByRole(t *testing.T) {
	store, err := OpenStore(filepath.Join(t.TempDir(), "classroom.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer store.Close()

	now := time.Now().UnixMilli()
	_, err = store.db.Exec(`
INSERT INTO lessons(classroom_id,title,description,homework_text,position,published,created_at,updated_at)
VALUES
('101','Draft','','',1,0,?,?),
('101','Published','','',2,1,?,?)`, now, now, now, now)
	if err != nil {
		t.Fatal(err)
	}

	studentRecorder := httptest.NewRecorder()
	listLessons(store)(studentRecorder, requestWithClassroom("/api/v1/classrooms/101/lessons", "101", "student"))
	if studentRecorder.Code != 200 {
		t.Fatalf("student list status=%d body=%s", studentRecorder.Code, studentRecorder.Body.String())
	}
	var studentRows []LessonRecord
	if err := json.Unmarshal(studentRecorder.Body.Bytes(), &studentRows); err != nil {
		t.Fatal(err)
	}
	if len(studentRows) != 1 || studentRows[0].Title != "Published" {
		t.Fatalf("student should see only published lesson: %#v", studentRows)
	}

	teacherRecorder := httptest.NewRecorder()
	listLessons(store)(teacherRecorder, requestWithClassroom("/api/v1/classrooms/101/lessons", "101", "teacher"))
	if teacherRecorder.Code != 200 {
		t.Fatalf("teacher list status=%d body=%s", teacherRecorder.Code, teacherRecorder.Body.String())
	}
	var teacherRows []LessonRecord
	if err := json.Unmarshal(teacherRecorder.Body.Bytes(), &teacherRows); err != nil {
		t.Fatal(err)
	}
	if len(teacherRows) != 2 {
		t.Fatalf("teacher should see draft and published lessons: %#v", teacherRows)
	}
}
