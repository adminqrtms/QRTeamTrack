@echo off
rem Opens the Windows Firewall ports QRTeamTrack needs (asks for Administrator).
powershell -NoProfile -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -NoExit -File \"%~dp0scripts\setup-firewall.ps1\"'"
