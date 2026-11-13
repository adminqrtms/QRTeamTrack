# One-time setup of the ngrok tunnel: gives the server a fixed internet address
# so the app works on any Wi-Fi or mobile data.
$dir = Join-Path $PSScriptRoot '..\tools\ngrok'
New-Item -ItemType Directory -Force -Path $dir | Out-Null

$exe = & (Join-Path $PSScriptRoot 'find-ngrok.ps1')
if (-not $exe) {
    Write-Host ''
    Write-Host 'ngrok.exe was not found.' -ForegroundColor Yellow
    Write-Host '  1. In the ngrok dashboard, open "Setup & Installation" and download ngrok for Windows (zip).'
    Write-Host '  2. Unzip it and put ngrok.exe in this folder:'
    Write-Host "     $((Resolve-Path $dir).Path)"
    Write-Host '  3. Run setup-ngrok.bat again.'
    exit 1
}
Write-Host "Using $exe"
Write-Host ''

Write-Host 'Step 1: your Authtoken (ngrok dashboard > "Your Authtoken").' -ForegroundColor Cyan
Write-Host '        Keep it private: paste it only here, never in chats or screenshots.'
$token = (Read-Host 'Authtoken').Trim()
if (-not $token) { Write-Host 'No authtoken entered.' -ForegroundColor Red; exit 1 }
& $exe config add-authtoken $token
if ($LASTEXITCODE -ne 0) { Write-Host 'ngrok did not accept the authtoken.' -ForegroundColor Red; exit 1 }

Write-Host ''
Write-Host 'Step 2: your free fixed domain (ngrok dashboard > "Domains"),' -ForegroundColor Cyan
Write-Host '        for example  something-something.ngrok-free.app'
$domain = (Read-Host 'Domain').Trim() -replace '^https?://', '' -replace '/.*$', ''
if ($domain -notmatch '^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$') {
    Write-Host "That doesn't look like a domain: '$domain'" -ForegroundColor Red
    exit 1
}
Set-Content -Path (Join-Path $dir 'domain.txt') -Value $domain -Encoding ascii

Write-Host ''
Write-Host "Done. The server's internet address is https://$domain" -ForegroundColor Green
Write-Host 'Next:'
Write-Host '  1. Close the old server windows and run start-server.bat'
Write-Host '  2. Run run-app.bat once to install the app with this address.'
Write-Host '     After that the app works on any Wi-Fi or mobile data.'
