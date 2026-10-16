package main

import (
	"bytes"
	"net/http"
	"net/http/httptest"
	"testing"

	"golang.org/x/crypto/bcrypt"
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


func TestAdminLoginUsesPassword(t *testing.T) {
	hash, err := bcrypt.GenerateFromPassword([]byte("secret-pass"), bcrypt.DefaultCost)
	if err != nil {
		t.Fatal(err)
	}
	cfg := Config{
		TeacherToken:      "0123456789abcdef0123456789abcdef",
		AdminPasswordHash: string(hash),
	}

	req := httptest.NewRequest(http.MethodPost, "/api/admin/login", bytes.NewBufferString(`{"password":"secret-pass"}`))
	rec := httptest.NewRecorder()
	adminLogin(cfg)(rec, req)
	if rec.Code != http.StatusOK {
		t.Fatalf("expected password login success, status=%d body=%s", rec.Code, rec.Body.String())
	}
	if len(rec.Result().Cookies()) == 0 {
		t.Fatal("expected admin cookie")
	}

	badReq := httptest.NewRequest(http.MethodPost, "/api/admin/login", bytes.NewBufferString(`{"password":"wrong"}`))
	badRec := httptest.NewRecorder()
	adminLogin(cfg)(badRec, badReq)
	if badRec.Code != http.StatusUnauthorized {
		t.Fatalf("wrong password must fail, status=%d", badRec.Code)
	}

	tokenReq := httptest.NewRequest(http.MethodPost, "/api/admin/login", bytes.NewBufferString(`{"token":"0123456789abcdef0123456789abcdef"}`))
	tokenRec := httptest.NewRecorder()
	adminLogin(cfg)(tokenRec, tokenReq)
	if tokenRec.Code != http.StatusUnauthorized {
		t.Fatalf("teacher token must not authenticate web login, status=%d", tokenRec.Code)
	}
}
