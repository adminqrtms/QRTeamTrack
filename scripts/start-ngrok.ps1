# Publishes the server at the fixed ngrok address (through the gateway on port 8090).
$exe = & (Join-Path $PSScriptRoot 'find-ngrok.ps1')
$domain = & (Join-Path $PSScriptRoot 'ngrok-domain.ps1')
if (-not $exe -or -not $domain) {
    Write-Host 'ngrok is not set up. Run setup-ngrok.bat first.' -ForegroundColor Yellow
    exit 0
}
Write-Host "Internet address: https://$domain" -ForegroundColor Green
Write-Host 'Phones reach the server through this address on any Wi-Fi or mobile data.'
Write-Host 'Keep this window open.'
Write-Host ''
& $exe http 8090 --url "https://$domain"
