package main

import (
	"encoding/json"
	"net/http"
	"strings"
	"sync"
)

type adminPINManager struct {
	mu         sync.RWMutex
	cfg        *Config
	configPath string
}

func newAdminPINManager(cfg *Config, configPath string) *adminPINManager {
	return &adminPINManager{cfg: cfg, configPath: configPath}
}

func (m *adminPINManager) teacherPIN() string {
	m.mu.RLock()
	defer m.mu.RUnlock()
	return m.cfg.TeacherPIN
}

func (m *adminPINManager) studentPIN() string {
	m.mu.RLock()
	defer m.mu.RUnlock()
	return m.cfg.StudentPIN
}

func (m *adminPINManager) getHandler() http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		m.mu.RLock()
		out := map[string]string{
			"teacher_pin": m.cfg.TeacherPIN,
			"student_pin": m.cfg.StudentPIN,
		}
		m.mu.RUnlock()
		writeJSON(w, out)
	}
}

func (m *adminPINManager) regenerateHandler() http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		var req struct {
			Role string `json:"role"`
		}
		if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 4096)).Decode(&req); err != nil {
			http.Error(w, "bad json", http.StatusBadRequest)
			return
		}
		role := strings.TrimSpace(req.Role)
		if role != "teacher" && role != "student" {
			http.Error(w, "bad role", http.StatusBadRequest)
			return
		}

		newPIN := randomNumericPIN(6)

		m.mu.Lock()
		oldTeacher := m.cfg.TeacherPIN
		oldStudent := m.cfg.StudentPIN
		if role == "teacher" {
			m.cfg.TeacherPIN = newPIN
		} else {
			m.cfg.StudentPIN = newPIN
		}
		if err := writeConfig(m.configPath, *m.cfg); err != nil {
			m.cfg.TeacherPIN = oldTeacher
			m.cfg.StudentPIN = oldStudent
			m.mu.Unlock()
			http.Error(w, "failed to save config", http.StatusInternalServerError)
			return
		}
		out := map[string]string{
			"teacher_pin": m.cfg.TeacherPIN,
			"student_pin": m.cfg.StudentPIN,
		}
		m.mu.Unlock()

		writeJSON(w, out)
	}
}
