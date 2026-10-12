package main

import (
	"archive/zip"
	"bufio"
	"context"
	"fmt"
	"io"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"
)

func recordingFiles(root, lesson, student string) ([]string, error) {
	dir := filepath.Join(root, safe(lesson), safe(student))
	entries, err := os.ReadDir(dir)
	if err != nil {
		return nil, err
	}

	files := make([]string, 0, len(entries))
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		name := strings.ToLower(e.Name())
		if strings.HasSuffix(name, ".jpg") || strings.HasSuffix(name, ".jpeg") || strings.HasSuffix(name, ".webp") {
			files = append(files, filepath.Join(dir, e.Name()))
		}
	}
	sort.Strings(files)
	return files, nil
}

func recordingZip(root string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		lesson := chi.URLParam(r, "lesson")
		student := chi.URLParam(r, "student")
		files, err := recordingFiles(root, lesson, student)
		if err != nil || len(files) == 0 {
			http.NotFound(w, r)
			return
		}

		w.Header().Set("Content-Type", "application/zip")
		w.Header().Set("Content-Disposition", fmt.Sprintf(
			"attachment; filename=\"lesson-%s-student-%s.zip\"", safe(lesson), safe(student)))

		zw := zip.NewWriter(w)
		defer zw.Close()

		for _, path := range files {
			src, openErr := os.Open(path)
			if openErr != nil {
				continue
			}
			dst, createErr := zw.Create(filepath.Base(path))
			if createErr == nil {
				_, _ = io.Copy(dst, src)
			}
			src.Close()
		}
	}
}

func recordingMP4(root string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		lesson := chi.URLParam(r, "lesson")
		student := chi.URLParam(r, "student")
		files, err := recordingFiles(root, lesson, student)
		if err != nil || len(files) == 0 {
			http.NotFound(w, r)
			return
		}

		tmpDir, err := os.MkdirTemp("", "classroom-mp4-*")
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}
		defer os.RemoveAll(tmpDir)

		listPath := filepath.Join(tmpDir, "frames.txt")
		list, err := os.Create(listPath)
		if err != nil {
			http.Error(w, err.Error(), 500)
			return
		}

		bw := bufio.NewWriter(list)
		for _, path := range files {
			normalized := filepath.ToSlash(path)
			fmt.Fprintf(bw, "file '%s'\n", strings.ReplaceAll(normalized, "'", "'\\''"))
			fmt.Fprintln(bw, "duration 1")
		}
		last := filepath.ToSlash(files[len(files)-1])
		fmt.Fprintf(bw, "file '%s'\n", strings.ReplaceAll(last, "'", "'\\''"))
		_ = bw.Flush()
		_ = list.Close()

		outPath := filepath.Join(tmpDir, "recording.mp4")
		ctx, cancel := context.WithTimeout(r.Context(), 5*time.Minute)
		defer cancel()

		cmd := exec.CommandContext(ctx, "ffmpeg",
			"-hide_banner", "-loglevel", "error", "-y",
			"-f", "concat", "-safe", "0", "-i", listPath,
			"-vf", "scale=trunc(iw/2)*2:trunc(ih/2)*2,format=yuv420p",
			"-c:v", "libx264", "-preset", "veryfast",
			"-movflags", "+faststart",
			outPath,
		)
		if output, runErr := cmd.CombinedOutput(); runErr != nil {
			http.Error(w, fmt.Sprintf("ffmpeg: %v: %s", runErr, string(output)), 500)
			return
		}

		w.Header().Set("Content-Type", "video/mp4")
		w.Header().Set("Content-Disposition", fmt.Sprintf(
			"attachment; filename=\"lesson-%s-student-%s.mp4\"", safe(lesson), safe(student)))
		http.ServeFile(w, r, outPath)
	}
}
