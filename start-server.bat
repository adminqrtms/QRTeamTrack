@echo off
rem Starts the Laravel API and the Reverb chat server, each in its own window.
cd /d "%~dp0web\qrt"
start "QRT - Laravel API (port 8000)" cmd /k php artisan serve --host=0.0.0.0 --port=8000
start "QRT - Reverb chat server (port 8080)" cmd /k php artisan reverb:start
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\show-urls.ps1"
pause
