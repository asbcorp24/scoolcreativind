@echo off
cd /d "%~dp0"
if exist dist\classroom-server.exe (
  cd dist
  classroom-server.exe
) else (
  classroom-server.exe
)
pause
