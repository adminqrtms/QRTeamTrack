@echo off
rem Runs the mobile app against this PC's server (IP detected automatically).
rem Examples:  run-app.bat            (choose a device from the list)
rem            run-app.bat chrome
rem            run-app.bat 22101316G
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\run-app.ps1" %*
pause
