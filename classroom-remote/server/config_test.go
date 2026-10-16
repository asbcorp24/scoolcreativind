package main

import (
	"bytes"
	"os"
	"path/filepath"
	"testing"
)

func TestConfigSecretsPersist(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "config.json")

	cfg1, changed, err := loadConfig(path)
	if err != nil {
		t.Fatal(err)
	}
	if !changed {
		t.Fatal("first load must create config")
	}
	if cfg1.TeacherToken == "" || cfg1.StudentToken == "" || cfg1.TURNPassword == "" || cfg1.AdminPasswordHash == "" {
		t.Fatal("generated secrets are empty")
	}

	cfg2, changed, err := loadConfig(path)
	if err != nil {
		t.Fatal(err)
	}
	if changed {
		t.Fatal("second load should not rewrite complete config")
	}
	if cfg1.TeacherToken != cfg2.TeacherToken ||
		cfg1.StudentToken != cfg2.StudentToken ||
		cfg1.TURNPassword != cfg2.TURNPassword ||
		cfg1.AdminPasswordHash != cfg2.AdminPasswordHash {
		t.Fatal("generated secrets did not persist")
	}

	info, err := os.Stat(path)
	if err != nil {
		t.Fatal(err)
	}
	if info.Size() == 0 {
		t.Fatal("config file is empty")
	}
}


func TestConfigMigratesPlainAdminPasswordToHash(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "config.json")
	raw := `{
  "teacher_token":"teacher-secret",
  "student_token":"student-secret",
  "teacher_pin":"123456",
  "student_pin":"654321",
  "turn_password":"turn-secret",
  "admin_password":"my-web-password",
  "rtc_min_port":50000,
  "rtc_max_port":50100,
  "turn_min_port":49160,
  "turn_max_port":49260,
  "max_upload_mb":50
}`
	if err := os.WriteFile(path, []byte(raw), 0600); err != nil {
		t.Fatal(err)
	}
	cfg, changed, err := loadConfig(path)
	if err != nil {
		t.Fatal(err)
	}
	if !changed {
		t.Fatal("plaintext admin password must trigger config rewrite")
	}
	if cfg.AdminPassword != "" || cfg.AdminPasswordHash == "" {
		t.Fatal("admin password was not migrated to hash")
	}
	saved, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	if bytes.Contains(saved, []byte("my-web-password")) {
		t.Fatal("plaintext admin password remained in config")
	}
}
