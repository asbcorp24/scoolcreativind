package main

import (
	"crypto/tls"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"golang.org/x/crypto/acme/autocert"
)

func serveApplication(cfg Config, handler http.Handler) error {
	if strings.TrimSpace(cfg.Domain) == "" {
		log.Printf("HTTP mode: http://0.0.0.0%s", cfg.HTTPAddr)
		return http.ListenAndServe(cfg.HTTPAddr, handler)
	}

	cacheDir := filepath.Join(filepath.Dir(cfg.DBPath), "acme-cache")
	if err := os.MkdirAll(cacheDir, 0o700); err != nil {
		return err
	}

	manager := &autocert.Manager{
		Prompt:     autocert.AcceptTOS,
		HostPolicy: autocert.HostWhitelist(cfg.Domain),
		Cache:      autocert.DirCache(cacheDir),
		Email:      cfg.ACMEEmail,
	}

	httpAddr := cfg.HTTPAddr
	if httpAddr == "" || httpAddr == ":8080" {
		httpAddr = ":80"
	}

	go func() {
		redirect := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			target := "https://" + cfg.Domain + r.URL.RequestURI()
			http.Redirect(w, r, target, http.StatusPermanentRedirect)
		})
		log.Printf("ACME/HTTP listening on %s", httpAddr)
		if err := http.ListenAndServe(httpAddr, manager.HTTPHandler(redirect)); err != nil {
			log.Printf("HTTP/ACME stopped: %v", err)
		}
	}()

	httpsAddr := cfg.HTTPSAddr
	if httpsAddr == "" {
		httpsAddr = ":443"
	}

	server := &http.Server{
		Addr:    httpsAddr,
		Handler: handler,
		TLSConfig: &tls.Config{
			GetCertificate: manager.GetCertificate,
			MinVersion:     tls.VersionTLS12,
		},
	}

	log.Printf("HTTPS/WSS mode: https://%s%s", cfg.Domain, httpsAddr)
	return server.ListenAndServeTLS("", "")
}
