package main

import (
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestAdminSessionAutoLoginOnlyOnLoopback(t *testing.T) {
	cfg := Config{TeacherToken: "0123456789abcdef0123456789abcdef"}

	localReq := httptest.NewRequest(http.MethodGet, "http://127.0.0.1:8080/api/admin/session", nil)
	localReq.RemoteAddr = "127.0.0.1:12345"
	localRec := httptest.NewRecorder()
	adminSession(cfg)(localRec, localReq)
	if localRec.Code != http.StatusOK {
		t.Fatalf("loopback must auto-login, status=%d", localRec.Code)
	}
	cookies := localRec.Result().Cookies()
	if len(cookies) == 0 || cookies[0].Name != adminCookieName || !cookies[0].HttpOnly {
		t.Fatalf("expected HttpOnly admin cookie, got %#v", cookies)
	}

	remoteReq := httptest.NewRequest(http.MethodGet, "http://server/api/admin/session", nil)
	remoteReq.RemoteAddr = "192.168.1.50:54321"
	remoteRec := httptest.NewRecorder()
	adminSession(cfg)(remoteRec, remoteReq)
	if remoteRec.Code != http.StatusUnauthorized {
		t.Fatalf("remote request must require login, status=%d", remoteRec.Code)
	}
}
