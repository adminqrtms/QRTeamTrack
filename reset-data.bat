@echo off
rem Deletes ALL personnel and residents and everything they created
rem (reports, SOS alarms, chats, calls, attendance, schedules, audit logs).
rem Keeps the admin account(s) and the stations. Asks before deleting.
cd /d "%~dp0web\qrt"
php artisan qrt:reset-data
echo.
pause
