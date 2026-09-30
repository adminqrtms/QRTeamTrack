@echo off
rem Gets the latest code and updates the database and app packages.
cd /d "%~dp0"
echo Discarding files that Flutter regenerates automatically...
git checkout -- mobile/mobile_qrtms/pubspec.lock mobile/mobile_qrtms/macos mobile/mobile_qrtms/windows mobile/mobile_qrtms/linux mobile/mobile_qrtms/analysis_options.yaml
git pull || goto :failed
cd /d "%~dp0web\qrt"
call composer install --no-interaction
php artisan migrate --force || goto :failed
php artisan config:clear
cd /d "%~dp0mobile\mobile_qrtms"
call flutter pub get || goto :failed
echo.
echo Update complete. Restart start-server.bat and run-app.bat.
pause
exit /b 0
:failed
echo.
echo Update stopped because of the error above. Send a screenshot of this window.
pause
exit /b 1
