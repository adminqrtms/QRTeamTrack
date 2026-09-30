$ip = & (Join-Path $PSScriptRoot 'lan-ip.ps1')

Write-Host ''
Write-Host '  QRTeamTrack servers are starting in two new windows.' -ForegroundColor Green
Write-Host '  Keep both windows open while you use the app.'
Write-Host ''
Write-Host '  Web admin (this PC):   http://127.0.0.1:8000'
if ($ip) {
    Write-Host "  Phone / emulator:      http://${ip}:8000"
    Write-Host ''
    Write-Host "  Test from the phone's browser: http://${ip}:8000/api/locations"
} else {
    Write-Host '  Could not detect this PC''s IP. Run ipconfig and use the Wi-Fi IPv4 Address.' -ForegroundColor Yellow
}
Write-Host ''
