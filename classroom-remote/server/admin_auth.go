package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net"
	"net/http"
	"strings"
	"time"
)

const adminCookieName = "classroom_admin"

func adminCookieValue(secret string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write([]byte("classroom-admin-session-v1"))
	return hex.EncodeToString(mac.Sum(nil))
}

func validAdminCookie(r *http.Request, cfg Config) bool {
	c, err := r.Cookie(adminCookieName)
	if err != nil || c.Value == "" {
		return false
	}
	expected := adminCookieValue(cfg.TeacherToken)
	return hmac.Equal([]byte(c.Value), []byte(expected))
}

func setAdminCookie(w http.ResponseWriter, r *http.Request, cfg Config) {
	http.SetCookie(w, &http.Cookie{
		Name:     adminCookieName,
		Value:    adminCookieValue(cfg.TeacherToken),
		Path:     "/",
		HttpOnly: true,
		Secure:   r.TLS != nil || strings.TrimSpace(cfg.Domain) != "",
		SameSite: http.SameSiteStrictMode,
		MaxAge:   30 * 24 * 60 * 60,
		Expires:  time.Now().Add(30 * 24 * time.Hour),
	})
}

func isLoopbackRequest(r *http.Request) bool {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		host = r.RemoteAddr
	}
	ip := net.ParseIP(host)
	return ip != nil && ip.IsLoopback()
}

func adminAuthorized(r *http.Request, cfg Config) bool {
	if validAdminCookie(r, cfg) {
		return true
	}
	token := bearerToken(r)
	return token != "" && constantTokenEqual(token, cfg.TeacherToken)
}

func constantTokenEqual(a, b string) bool {
	if a == "" || b == "" || len(a) != len(b) {
		return false
	}
	return hmac.Equal([]byte(a), []byte(b))
}

func requireAdmin(cfg Config, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !adminAuthorized(r, cfg) {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		next(w, r)
	}
}

func adminSession(cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if validAdminCookie(r, cfg) {
			writeJSON(w, map[string]any{"ok": true, "automatic": false})
			return
		}
		if isLoopbackRequest(r) {
			setAdminCookie(w, r, cfg)
			writeJSON(w, map[string]any{"ok": true, "automatic": true})
			return
		}
		http.Error(w, "login required", http.StatusUnauthorized)
	}
}

func adminLogin(cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		var req struct {
			Token string `json:"token"`
		}
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			http.Error(w, "bad json", 400)
			return
		}
		if !constantTokenEqual(strings.TrimSpace(req.Token), cfg.TeacherToken) {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		setAdminCookie(w, r, cfg)
		writeJSON(w, map[string]any{"ok": true})
	}
}

func adminLogout(cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		http.SetCookie(w, &http.Cookie{
			Name:     adminCookieName,
			Value:    "",
			Path:     "/",
			HttpOnly: true,
			Secure:   r.TLS != nil || strings.TrimSpace(cfg.Domain) != "",
			SameSite: http.SameSiteStrictMode,
			MaxAge:   -1,
		})
		writeJSON(w, map[string]any{"ok": true})
	}
}
