@echo off
rem Builds QRTeamTrack.apk: an app file you can share and install on any phone.
rem   build-apk.bat              uses the ngrok internet address (any Wi-Fi or mobile data)
rem   build-apk.bat 192.168.1.15 uses this Wi-Fi IP instead
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\build-apk.ps1" %*
echo.
pause
