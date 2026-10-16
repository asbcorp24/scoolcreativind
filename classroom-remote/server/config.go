package main

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"math/big"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

type Config struct {
	HTTPAddr       string `json:"http_addr"`
	HTTPSAddr      string `json:"https_addr"`
	Domain         string `json:"domain"`
	ACMEEmail      string `json:"acme_email"`
	PublicIP       string `json:"public_ip"`
	StorageDir     string `json:"storage_dir"`
	DBPath         string `json:"db_path"`
	TeacherToken   string `json:"teacher_token"`
	StudentToken   string `json:"student_token"`
	TeacherPIN     string `json:"teacher_pin"`
	StudentPIN     string `json:"student_pin"`
	RTCMinPort     int    `json:"rtc_min_port"`
	RTCMaxPort     int    `json:"rtc_max_port"`
	TURNEnabled    bool   `json:"turn_enabled"`
	TURNListen     string `json:"turn_listen"`
	TURNRealm      string `json:"turn_realm"`
	TURNUsername   string `json:"turn_username"`
	TURNPassword   string `json:"turn_password"`
	TURNMinPort    int    `json:"turn_min_port"`
	TURNMaxPort    int    `json:"turn_max_port"`
	FFmpegPath     string `json:"ffmpeg_path"`
	MaxUploadMB    int    `json:"max_upload_mb"`
}

func defaultConfig() Config {
	return Config{
		HTTPAddr:     ":8080",
		HTTPSAddr:    ":443",
		StorageDir:   "./data/storage",
		DBPath:       "./data/classroom.db",
		RTCMinPort:   50000,
		RTCMaxPort:   50100,
		TURNEnabled:  true,
		TURNListen:   ":3478",
		TURNRealm:    "classroom",
		TURNUsername: "classroom",
		TURNMinPort:  49160,
		TURNMaxPort:  49260,
		MaxUploadMB:  50,
	}
}

func loadConfig(path string) (Config, bool, error) {
	cfg := defaultConfig()
	changed := false

	if b, err := os.ReadFile(path); err == nil {
		if err := json.Unmarshal(b, &cfg); err != nil {
			return Config{}, false, fmt.Errorf("parse %s: %w", path, err)
		}
	} else if !os.IsNotExist(err) {
		return Config{}, false, err
	} else {
		changed = true
	}

	if cfg.TeacherToken == "" {
		cfg.TeacherToken = randomSecret(32)
		changed = true
	}
	if cfg.StudentToken == "" {
		cfg.StudentToken = randomSecret(32)
		changed = true
	}
	if cfg.TeacherPIN == "" {
		cfg.TeacherPIN = randomNumericPIN(6)
		changed = true
	}
	if cfg.StudentPIN == "" {
		cfg.StudentPIN = randomNumericPIN(6)
		changed = true
	}
	if cfg.TURNPassword == "" {
		cfg.TURNPassword = randomSecret(24)
		changed = true
	}
	if cfg.MaxUploadMB <= 0 {
		cfg.MaxUploadMB = 50
		changed = true
	}

	if err := normalizeConfig(&cfg); err != nil {
		return Config{}, false, err
	}
	if changed {
		if err := writeConfig(path, cfg); err != nil {
			return Config{}, false, err
		}
	}

	applyEnvOverrides(&cfg)
	if err := normalizeConfig(&cfg); err != nil {
		return Config{}, false, err
	}
	return cfg, changed, nil
}

func normalizeConfig(cfg *Config) error {
	if cfg.HTTPAddr == "" {
		cfg.HTTPAddr = ":8080"
	}
	if cfg.HTTPSAddr == "" {
		cfg.HTTPSAddr = ":443"
	}
	if cfg.StorageDir == "" {
		cfg.StorageDir = "./data/storage"
	}
	if cfg.DBPath == "" {
		cfg.DBPath = "./data/classroom.db"
	}
	if cfg.RTCMinPort <= 0 || cfg.RTCMaxPort < cfg.RTCMinPort || cfg.RTCMaxPort > 65535 {
		return fmt.Errorf("invalid WebRTC port range %d-%d", cfg.RTCMinPort, cfg.RTCMaxPort)
	}
	if cfg.TURNMinPort <= 0 || cfg.TURNMaxPort < cfg.TURNMinPort || cfg.TURNMaxPort > 65535 {
		return fmt.Errorf("invalid TURN port range %d-%d", cfg.TURNMinPort, cfg.TURNMaxPort)
	}
	if cfg.TURNListen == "" {
		cfg.TURNListen = ":3478"
	}
	if cfg.TURNRealm == "" {
		cfg.TURNRealm = "classroom"
	}
	if cfg.TURNUsername == "" {
		cfg.TURNUsername = "classroom"
	}
	if cfg.MaxUploadMB <= 0 {
		cfg.MaxUploadMB = 50
	}
	if cfg.MaxUploadMB > 1024 {
		return fmt.Errorf("max_upload_mb must be <= 1024")
	}
	if cfg.TeacherToken == "" || cfg.StudentToken == "" {
		return fmt.Errorf("teacher_token and student_token are required")
	}
	if !validPIN(cfg.TeacherPIN) || !validPIN(cfg.StudentPIN) {
		return fmt.Errorf("teacher_pin and student_pin must contain 4-10 digits")
	}

	for _, p := range []string{cfg.StorageDir, filepath.Dir(cfg.DBPath)} {
		if p != "" && p != "." {
			if err := os.MkdirAll(p, 0o755); err != nil {
				return err
			}
		}
	}
	return nil
}

func writeConfig(path string, cfg Config) error {
	if dir := filepath.Dir(path); dir != "." {
		if err := os.MkdirAll(dir, 0o755); err != nil {
			return err
		}
	}
	b, err := json.MarshalIndent(cfg, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, append(b, '\n'), 0o600)
}

func applyEnvOverrides(cfg *Config) {
	setString := func(name string, dst *string) {
		if v := strings.TrimSpace(os.Getenv(name)); v != "" {
			*dst = v
		}
	}
	setInt := func(name string, dst *int) {
		if v := strings.TrimSpace(os.Getenv(name)); v != "" {
			if n, err := strconv.Atoi(v); err == nil {
				*dst = n
			}
		}
	}

	setString("ADDR", &cfg.HTTPAddr)
	setString("PUBLIC_IP", &cfg.PublicIP)
	setString("STORAGE_DIR", &cfg.StorageDir)
	setString("TEACHER_TOKEN", &cfg.TeacherToken)
	setString("STUDENT_TOKEN", &cfg.StudentToken)
	setString("TEACHER_PIN", &cfg.TeacherPIN)
	setString("STUDENT_PIN", &cfg.StudentPIN)
	setInt("RTC_MIN_PORT", &cfg.RTCMinPort)
	setInt("RTC_MAX_PORT", &cfg.RTCMaxPort)
	setInt("MAX_UPLOAD_MB", &cfg.MaxUploadMB)
}

func randomSecret(bytes int) string {
	buf := make([]byte, bytes)
	if _, err := rand.Read(buf); err != nil {
		panic(err)
	}
	return hex.EncodeToString(buf)
}

func randomNumericPIN(length int) string {
	if length < 4 {
		length = 6
	}
	out := make([]byte, length)
	for i := range out {
		n, err := rand.Int(rand.Reader, big.NewInt(10))
		if err != nil {
			panic(err)
		}
		out[i] = byte('0' + n.Int64())
	}
	return string(out)
}

func validPIN(pin string) bool {
	if len(pin) < 4 || len(pin) > 10 {
		return false
	}
	for _, r := range pin {
		if r < '0' || r > '9' {
			return false
		}
	}
	return true
}
