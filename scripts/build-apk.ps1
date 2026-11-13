# Builds an installable app file (APK) that you can share by Messenger, Google Drive, etc.
# With ngrok set up (setup-ngrok.bat) it uses the fixed internet address, so it works
# on any Wi-Fi or mobile data. Usage: build-apk.bat            (ngrok address)
#                                     build-apk.bat 192.168.1.15  (this Wi-Fi only)
param([string]$ServerIp = '')

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$ngrokDomain = & (Join-Path $PSScriptRoot 'ngrok-domain.ps1')

if ($ServerIp) {
    $apiUrl = "http://${ServerIp}:8000/api"
    Write-Host ''
    Write-Host "  Building for $apiUrl (works only on this PC's Wi-Fi)" -ForegroundColor Yellow
} elseif ($ngrokDomain) {
    $apiUrl = "https://$ngrokDomain/api"
    Write-Host ''
    Write-Host "  Building for $apiUrl (any Wi-Fi or mobile data)" -ForegroundColor Green
} else {
    Write-Host ''
    Write-Host 'ngrok is not set up, so the app would only work on this Wi-Fi.' -ForegroundColor Yellow
    Write-Host 'Run setup-ngrok.bat first, or build for a Wi-Fi IP: build-apk.bat <your-ip>'
    exit 1
}
Write-Host '  This takes a few minutes the first time.'
Write-Host ''

Set-Location (Join-Path $root 'mobile\mobile_qrtms')
& flutter pub get
if ($LASTEXITCODE -ne 0) { Write-Host 'flutter pub get failed.' -ForegroundColor Red; exit 1 }

& flutter build apk --release "--dart-define=API_URL=$apiUrl"
$buildOk = $LASTEXITCODE -eq 0
git checkout -- analysis_options.yaml 2>$null
if (-not $buildOk) { Write-Host 'The build failed (see the messages above).' -ForegroundColor Red; exit 1 }

$apk = Join-Path $root 'QRTeamTrack.apk'
Copy-Item 'build\app\outputs\flutter-apk\app-release.apk' $apk -Force
$sizeMb = [math]::Round((Get-Item $apk).Length / 1MB, 1)

Write-Host ''
Write-Host "  Done: $apk ($sizeMb MB)" -ForegroundColor Green
Write-Host '  Send this file to the phones (Messenger, Google Drive, USB). On the phone:'
Write-Host '    tap the file > allow "Install unknown apps" if asked > Install.'
Write-Host '  The server (start-server.bat, with ngrok) must be running on this PC.'
