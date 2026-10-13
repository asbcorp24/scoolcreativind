#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")"
mkdir -p dist
CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o dist/classroom-server .
echo "READY: $(pwd)/dist/classroom-server"
