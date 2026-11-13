@echo off
rem Starts the Laravel API, the Reverb chat server and (if installed) the LiveKit call server.
cd /d "%~dp0web\qrt"
rem Makes uploaded photos (avatars, reports) reachable at /storage
if not exist "public\storage" php artisan storage:link >nul 2>&1
start "QRT - Laravel API (port 8000)" cmd /k php artisan serve --host=0.0.0.0 --port=8000
start "QRT - Reverb chat server (port 8080)" cmd /k php artisan reverb:start
if exist "%~dp0tools\livekit\livekit-server.exe" start "QRT - LiveKit call server (port 7880)" powershell -NoProfile -ExecutionPolicy Bypass -NoExit -File "%~dp0scripts\start-livekit.ps1"
rem Internet address with ngrok (set up with setup-ngrok.bat)
if exist "%~dp0tools\ngrok\domain.txt" start "QRT - Gateway for ngrok (port 8090)" cmd /k php artisan qrt:gateway
if exist "%~dp0tools\ngrok\domain.txt" start "QRT - ngrok internet address" powershell -NoProfile -ExecutionPolicy Bypass -NoExit -File "%~dp0scripts\start-ngrok.ps1"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\show-urls.ps1"
pause
