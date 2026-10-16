package main

import (
    "crypto/subtle"
    "encoding/json"
    "net"
    "net/http"
    "strings"
    "sync"
    "time"
)

type pinAttempt struct {
    Count int
    Since time.Time
}

type pinLimiter struct {
    mu sync.Mutex
    byIP map[string]pinAttempt
}

func newPINLimiter() *pinLimiter {
    return &pinLimiter{byIP: map[string]pinAttempt{}}
}

func (l *pinLimiter) allow(ip string) bool {
    l.mu.Lock()
    defer l.mu.Unlock()
    now := time.Now()
    a := l.byIP[ip]
    if a.Since.IsZero() || now.Sub(a.Since) > time.Minute {
        l.byIP[ip] = pinAttempt{Since: now}
        return true
    }
    return a.Count < 10
}

func (l *pinLimiter) fail(ip string) {
    l.mu.Lock()
    defer l.mu.Unlock()
    now := time.Now()
    a := l.byIP[ip]
    if a.Since.IsZero() || now.Sub(a.Since) > time.Minute {
        a = pinAttempt{Since: now}
    }
    a.Count++
    l.byIP[ip] = a
}

func requestIP(r *http.Request) string {
    if xf := strings.TrimSpace(strings.Split(r.Header.Get("X-Forwarded-For"), ",")[0]); xf != "" {
        return xf
    }
    host, _, err := net.SplitHostPort(r.RemoteAddr)
    if err == nil && host != "" {
        return host
    }
    return r.RemoteAddr
}

func sameSecret(a, b string) bool {
    return a != "" && b != "" && len(a) == len(b) &&
        subtle.ConstantTimeCompare([]byte(a), []byte(b)) == 1
}

func pinLogin(cfg Config, limiter *pinLimiter) http.HandlerFunc {
    type request struct {
        Role     string `json:"role"`
        PIN      string `json:"pin"`
        LessonID string `json:"lesson_id"`
    }
    return func(w http.ResponseWriter, r *http.Request) {
        ip := requestIP(r)
        if !limiter.allow(ip) {
            http.Error(w, "too many attempts", http.StatusTooManyRequests)
            return
        }

        var in request
        dec := json.NewDecoder(http.MaxBytesReader(w, r.Body, 4096))
        if err := dec.Decode(&in); err != nil {
            http.Error(w, "bad request", http.StatusBadRequest)
            return
        }
        in.Role = strings.TrimSpace(in.Role)
        in.PIN = strings.TrimSpace(in.PIN)
        in.LessonID = strings.TrimSpace(in.LessonID)
        if in.LessonID == "" {
            in.LessonID = "101"
        }

        token := ""
        valid := false
        switch in.Role {
        case "teacher":
            valid = sameSecret(in.PIN, cfg.TeacherPIN)
            token = cfg.TeacherToken
        case "student":
            valid = sameSecret(in.PIN, cfg.StudentPIN)
            token = cfg.StudentToken
        default:
            http.Error(w, "bad role", http.StatusBadRequest)
            return
        }
        if !valid {
            limiter.fail(ip)
            http.Error(w, "invalid pin", http.StatusUnauthorized)
            return
        }

        proto := "http"
        if r.TLS != nil {
            proto = "https"
        }
        if xf := strings.TrimSpace(r.Header.Get("X-Forwarded-Proto")); xf == "https" || xf == "http" {
            proto = xf
        }

        host := r.Host
        if cfg.Domain != "" {
            host = cfg.Domain
        }
        httpURL := proto + "://" + host
        wsProto := "ws"
        if proto == "https" {
            wsProto = "wss"
        }
        wsURL := wsProto + "://" + host + "/ws"

        w.Header().Set("Content-Type", "application/json; charset=utf-8")
        _ = json.NewEncoder(w).Encode(map[string]any{
            "ok":            true,
            "role":          in.Role,
            "lesson_id":     in.LessonID,
            "http_url":      httpURL,
            "ws_url":        wsURL,
            "access_token":  token,
            "max_upload_mb": cfg.MaxUploadMB,
        })
    }
}
