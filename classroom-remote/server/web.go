package main

import (
	"embed"
	"encoding/json"
	"io/fs"
	"net/http"
)

//go:embed web/*
var embeddedWeb embed.FS

func webHandler() http.Handler {
	sub, err := fs.Sub(embeddedWeb, "web")
	if err != nil {
		panic(err)
	}
	return http.FileServer(http.FS(sub))
}

func adminStatus(h *Hub, cfg Config, turnServer *EmbeddedTURN) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		users := h.snapshot()
		turnEnabled := turnServer != nil
		_ = json.NewEncoder(w).Encode(map[string]any{
			"users":        users,
			"online":       len(users),
			"public_ip":    cfg.PublicIP,
			"turn_enabled": turnEnabled,
			"rtc_ports":    []int{cfg.RTCMinPort, cfg.RTCMaxPort},
			"max_upload_mb": cfg.MaxUploadMB,
		})
	}
}
