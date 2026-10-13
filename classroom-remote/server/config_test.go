package main

import (
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
	if cfg1.TeacherToken == "" || cfg1.StudentToken == "" || cfg1.TURNPassword == "" {
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
		cfg1.TURNPassword != cfg2.TURNPassword {
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
