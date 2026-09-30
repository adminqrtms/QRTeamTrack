# Starts the LiveKit call server (dev mode) so phones on this Wi-Fi can connect.
# Expects tools\livekit\livekit-server.exe (download from LiveKit's GitHub releases).
$exe = Join-Path $PSScriptRoot '..\tools\livekit\livekit-server.exe'
if (-not (Test-Path $exe)) {
    Write-Host 'LiveKit is not installed yet (tools\livekit\livekit-server.exe). Calls are disabled.' -ForegroundColor Yellow
    exit 0
}

$ip = & (Join-Path $PSScriptRoot 'lan-ip.ps1')
$flags = @('--dev', '--bind', '0.0.0.0')
if ($ip) {
    # The address phones use to send audio/video to this PC
    $flags += @('--node-ip', $ip)
}

Write-Host "Starting LiveKit on port 7880 (API key: devkey, secret: secret)"
if ($ip) {
    Write-Host "Phones reach calls at $ip (must match the IP run-app.bat used)." -ForegroundColor Green
    Write-Host "If you change Wi-Fi, close this window and run start-server.bat again."
}
& $exe @flags
