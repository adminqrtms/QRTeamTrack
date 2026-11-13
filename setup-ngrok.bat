@echo off
rem One-time setup: gives the server a fixed internet address with ngrok,
rem so the app works on any Wi-Fi or mobile data.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup-ngrok.ps1"
echo.
pause
