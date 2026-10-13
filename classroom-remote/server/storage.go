package main

import (
	"database/sql"
	"time"

	_ "modernc.org/sqlite"
)

type Store struct {
	db *sql.DB
}

func OpenStore(path string) (*Store, error) {
	db, err := sql.Open("sqlite", path)
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(1)

	s := &Store{db: db}
	if err := s.migrate(); err != nil {
		_ = db.Close()
		return nil, err
	}
	return s, nil
}

func (s *Store) Close() error {
	if s == nil || s.db == nil {
		return nil
	}
	return s.db.Close()
}

func (s *Store) migrate() error {
	_, err := s.db.Exec(`
PRAGMA journal_mode=WAL;
PRAGMA busy_timeout=5000;

CREATE TABLE IF NOT EXISTS snapshots (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	lesson_id TEXT NOT NULL,
	student_id TEXT NOT NULL,
	file_name TEXT NOT NULL,
	captured_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_snapshots_lesson_student_time
	ON snapshots(lesson_id, student_id, captured_at);

CREATE TABLE IF NOT EXISTS audit_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	lesson_id TEXT NOT NULL,
	actor_id TEXT NOT NULL,
	target_id TEXT,
	event TEXT NOT NULL,
	created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_lesson_time
	ON audit_log(lesson_id, created_at);
`)
	return err
}

func (s *Store) AddSnapshot(lessonID, studentID, fileName string, ts int64) {
	if s == nil || s.db == nil {
		return
	}
	_, _ = s.db.Exec(
		`INSERT INTO snapshots(lesson_id,student_id,file_name,captured_at) VALUES(?,?,?,?)`,
		lessonID, studentID, fileName, ts,
	)
}

func (s *Store) Audit(lessonID, actorID, targetID, event string) {
	if s == nil || s.db == nil {
		return
	}
	_, _ = s.db.Exec(
		`INSERT INTO audit_log(lesson_id,actor_id,target_id,event,created_at) VALUES(?,?,?,?,?)`,
		lessonID, actorID, targetID, event, time.Now().UTC().UnixMilli(),
	)
}
