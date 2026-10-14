package main

import (
	"encoding/json"
	"fmt"
	"html"
	"net/http"
	"strings"

	"github.com/go-chi/chi/v5"
)

type inviteCreateRequest struct {
	LessonID string `json:"lesson_id"`
	Role     string `json:"role"`
}

func requestBaseURL(r *http.Request, cfg Config) string {
	if strings.TrimSpace(cfg.Domain) != "" {
		return "https://" + cfg.Domain
	}
	scheme := "http"
	if r.TLS != nil {
		scheme = "https"
	}
	if forwarded := strings.TrimSpace(r.Header.Get("X-Forwarded-Proto")); forwarded != "" {
		scheme = forwarded
	}
	return scheme + "://" + r.Host
}

func websocketBaseURL(httpBase string) string {
	if strings.HasPrefix(httpBase, "https://") {
		return "wss://" + strings.TrimPrefix(httpBase, "https://") + "/ws"
	}
	return "ws://" + strings.TrimPrefix(httpBase, "http://") + "/ws"
}

func createInvitation(store *Store, cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		var req inviteCreateRequest
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			http.Error(w, "bad json", http.StatusBadRequest)
			return
		}
		req.LessonID = strings.TrimSpace(req.LessonID)
		req.Role = strings.TrimSpace(req.Role)
		if req.LessonID == "" || (req.Role != "teacher" && req.Role != "student") {
			http.Error(w, "lesson_id and valid role are required", http.StatusBadRequest)
			return
		}

		inv, err := store.CreateInvitation(req.Role, req.LessonID)
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}
		base := requestBaseURL(r, cfg)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"code":      inv.Code,
			"role":      inv.Role,
			"lesson_id": inv.LessonID,
			"url":       base + "/join/" + inv.Code,
		})
	}
}

func resolveInvitation(store *Store, cfg Config) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		code := chi.URLParam(r, "code")
		inv, ok := store.ResolveInvitation(code)
		if !ok {
			http.NotFound(w, r)
			return
		}
		httpBase := requestBaseURL(r, cfg)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"code":         inv.Code,
			"role":         inv.Role,
			"lesson_id":    inv.LessonID,
			"access_token": inv.Code,
			"http_url":     httpBase,
			"ws_url":       websocketBaseURL(httpBase),
		})
	}
}

func joinInvitationPage(store *Store) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		code := chi.URLParam(r, "code")
		inv, ok := store.ResolveInvitation(code)
		if !ok {
			http.Error(w, "Ссылка недействительна или была отозвана", http.StatusNotFound)
			return
		}

		scheme := "classroomstudent"
		title := "Подключиться как ученик"
		if inv.Role == "teacher" {
			scheme = "classroomteacher"
			title = "Подключиться как преподаватель"
		}

		inviteURL := requestBaseURL(r, Config{}) + "/join/" + code
		deepLink := fmt.Sprintf("%s://join?invite=%s", scheme, urlQueryEscape(inviteURL))

		w.Header().Set("Content-Type", "text/html; charset=utf-8")
		_, _ = fmt.Fprintf(w, `<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>%s</title>
<style>
body{font-family:system-ui;background:#0b1120;color:#e5e7eb;margin:0;display:grid;place-items:center;min-height:100vh}
.card{width:min(560px,92vw);background:#111827;border:1px solid #334155;border-radius:18px;padding:28px}
button,a{display:block;width:100%%;box-sizing:border-box;padding:14px;border-radius:12px;border:0;text-align:center;text-decoration:none;font-weight:700}
.primary{background:#2563eb;color:white}.muted{color:#94a3b8}.copy{background:#1f2937;color:#e5e7eb;margin-top:12px}
code{word-break:break-all;color:#93c5fd}
</style>
</head>
<body><div class="card">
<h1>%s</h1>
<p>Занятие: <b>%s</b></p>
<p class="muted">Нажмите кнопку. Если приложение уже установлено, оно откроется и подключится автоматически.</p>
<a class="primary" href="%s">Открыть приложение</a>
<button class="copy" onclick="navigator.clipboard.writeText(location.href)">Скопировать ссылку</button>
<p class="muted">Ссылка доступа: <code>%s</code></p>
<script>
setTimeout(()=>{ location.href=%q; },600);
</script>
</div></body></html>`,
			html.EscapeString(title),
			html.EscapeString(title),
			html.EscapeString(inv.LessonID),
			html.EscapeString(deepLink),
			html.EscapeString(inviteURL),
			deepLink,
		)
	}
}

func urlQueryEscape(s string) string {
	replacer := strings.NewReplacer(
		"%", "%25",
		":", "%3A",
		"/", "%2F",
		"?", "%3F",
		"&", "%26",
		"=", "%3D",
		"#", "%23",
		" ", "%20",
	)
	return replacer.Replace(s)
}
