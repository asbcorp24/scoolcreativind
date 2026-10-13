@echo off
setlocal
cd /d "%~dp0"
if not exist dist mkdir dist
set CGO_ENABLED=0
set GOOS=windows
set GOARCH=amd64
go build -trimpath -ldflags="-s -w" -o dist\classroom-server.exe .
if errorlevel 1 exit /b 1
echo.
echo READY: %CD%\dist\classroom-server.exe
endlocal
