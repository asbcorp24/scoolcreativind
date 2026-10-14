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

CREATE TABLE IF NOT EXISTS invitations (
	code TEXT PRIMARY KEY,
	role TEXT NOT NULL,
	lesson_id TEXT NOT NULL,
	created_at INTEGER NOT NULL,
	revoked_at INTEGER
);
CREATE INDEX IF NOT EXISTS idx_invitations_lesson_role
	ON invitations(lesson_id, role, revoked_at);

CREATE TABLE IF NOT EXISTS lessons (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	classroom_id TEXT NOT NULL,
	title TEXT NOT NULL,
	description TEXT NOT NULL DEFAULT '',
	homework_text TEXT NOT NULL DEFAULT '',
	due_at INTEGER,
	position INTEGER NOT NULL DEFAULT 0,
	published INTEGER NOT NULL DEFAULT 0,
	created_at INTEGER NOT NULL,
	updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_lessons_classroom_position
	ON lessons(classroom_id, position, id);

CREATE TABLE IF NOT EXISTS lesson_files (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	lesson_id INTEGER NOT NULL,
	original_name TEXT NOT NULL,
	stored_name TEXT NOT NULL,
	mime_type TEXT NOT NULL DEFAULT '',
	size_bytes INTEGER NOT NULL,
	created_at INTEGER NOT NULL,
	FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_lesson_files_lesson ON lesson_files(lesson_id);

CREATE TABLE IF NOT EXISTS submissions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	lesson_id INTEGER NOT NULL,
	student_id TEXT NOT NULL,
	student_name TEXT NOT NULL,
	note TEXT NOT NULL DEFAULT '',
	submitted_at INTEGER NOT NULL,
	updated_at INTEGER NOT NULL,
	FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_submissions_lesson_student
	ON submissions(lesson_id, student_id, submitted_at);

CREATE TABLE IF NOT EXISTS submission_files (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	submission_id INTEGER NOT NULL,
	original_name TEXT NOT NULL,
	stored_name TEXT NOT NULL,
	mime_type TEXT NOT NULL DEFAULT '',
	size_bytes INTEGER NOT NULL,
	created_at INTEGER NOT NULL,
	FOREIGN KEY(submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_submission_files_submission
	ON submission_files(submission_id);
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


type Invitation struct {
	Code     string `json:"code"`
	Role     string `json:"role"`
	LessonID string `json:"lesson_id"`
}

func (s *Store) CreateInvitation(role, lessonID string) (Invitation, error) {
	code := randomSecret(18)
	now := time.Now().UTC().UnixMilli()

	tx, err := s.db.Begin()
	if err != nil {
		return Invitation{}, err
	}
	defer tx.Rollback()

	if _, err = tx.Exec(
		`UPDATE invitations SET revoked_at=? WHERE lesson_id=? AND role=? AND revoked_at IS NULL`,
		now, lessonID, role,
	); err != nil {
		return Invitation{}, err
	}
	if _, err = tx.Exec(
		`INSERT INTO invitations(code,role,lesson_id,created_at,revoked_at) VALUES(?,?,?,?,NULL)`,
		code, role, lessonID, now,
	); err != nil {
		return Invitation{}, err
	}
	if err = tx.Commit(); err != nil {
		return Invitation{}, err
	}
	return Invitation{Code: code, Role: role, LessonID: lessonID}, nil
}

func (s *Store) ResolveInvitation(code string) (Invitation, bool) {
	var inv Invitation
	err := s.db.QueryRow(
		`SELECT code, role, lesson_id FROM invitations WHERE code=? AND revoked_at IS NULL`,
		code,
	).Scan(&inv.Code, &inv.Role, &inv.LessonID)
	if err != nil {
		return Invitation{}, false
	}
	return inv, true
}

func (s *Store) ValidateInvitation(code, role, lessonID string) bool {
	var n int
	err := s.db.QueryRow(
		`SELECT COUNT(1) FROM invitations WHERE code=? AND role=? AND lesson_id=? AND revoked_at IS NULL`,
		code, role, lessonID,
	).Scan(&n)
	return err == nil && n == 1
}

func (s *Store) RevokeInvitation(code string) {
	_, _ = s.db.Exec(
		`UPDATE invitations SET revoked_at=? WHERE code=? AND revoked_at IS NULL`,
		time.Now().UTC().UnixMilli(), code,
	)
}
