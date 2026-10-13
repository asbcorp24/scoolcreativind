@echo off
net session >nul 2>&1
if errorlevel 1 (
  echo Run this file as Administrator.
  pause
  exit /b 1
)

netsh advfirewall firewall add rule name="Classroom Remote HTTP 8080" dir=in action=allow protocol=TCP localport=8080
netsh advfirewall firewall add rule name="Classroom Remote HTTPS 443" dir=in action=allow protocol=TCP localport=443
netsh advfirewall firewall add rule name="Classroom Remote ACME 80" dir=in action=allow protocol=TCP localport=80
netsh advfirewall firewall add rule name="Classroom Remote TURN UDP 3478" dir=in action=allow protocol=UDP localport=3478
netsh advfirewall firewall add rule name="Classroom Remote TURN TCP 3478" dir=in action=allow protocol=TCP localport=3478
netsh advfirewall firewall add rule name="Classroom Remote WebRTC UDP" dir=in action=allow protocol=UDP localport=50000-50100
netsh advfirewall firewall add rule name="Classroom Remote TURN Relay UDP" dir=in action=allow protocol=UDP localport=49160-49260

echo Firewall rules added.
pause
