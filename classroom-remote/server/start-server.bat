@echo off
cd /d "%~dp0"
start "" powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 2; Start-Process 'http://127.0.0.1:8080/'"
if exist dist\classroom-server.exe (
  cd dist
  classroom-server.exe
) else (
  classroom-server.exe
)
pause
