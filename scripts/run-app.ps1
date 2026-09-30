# Runs the Flutter app pointed at this PC's server, using the current IP.
# Usage: run-app.bat [device]   e.g.  run-app.bat chrome   /   run-app.bat 22101316G
param([string]$Device = '')

$ip = & (Join-Path $PSScriptRoot 'lan-ip.ps1')
if (-not $ip) {
    Write-Host 'Could not detect this PC''s IP address.' -ForegroundColor Yellow
    $ip = Read-Host 'Type the Wi-Fi IPv4 Address from ipconfig'
}

$apiUrl = "http://${ip}:8000/api"
Write-Host ''
Write-Host "  The app will use the server at $apiUrl" -ForegroundColor Green
Write-Host ''

Set-Location (Join-Path $PSScriptRoot '..\mobile\mobile_qrtms')

$flutterArgs = @('run', "--dart-define=API_URL=$apiUrl")
if ($Device) {
    $flutterArgs += @('-d', $Device)
}

# Without a device, Flutter lists the connected ones and asks which to use.
& flutter @flutterArgs
