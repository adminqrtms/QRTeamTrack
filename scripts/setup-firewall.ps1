# Opens the Windows Firewall for QRTeamTrack (run as Administrator).
# Safe to run more than once: existing QRT rules are replaced.
$ErrorActionPreference = 'Stop'

$rules = @(
    @{ Name = 'QRT Laravel API 8000'; Protocol = 'TCP'; Port = 8000 },
    @{ Name = 'QRT Reverb chat 8080'; Protocol = 'TCP'; Port = 8080 },
    @{ Name = 'QRT LiveKit 7880'; Protocol = 'TCP'; Port = 7880 },
    @{ Name = 'QRT LiveKit media TCP 7881'; Protocol = 'TCP'; Port = 7881 },
    @{ Name = 'QRT LiveKit media UDP 7882'; Protocol = 'UDP'; Port = 7882 }
)

foreach ($rule in $rules) {
    Get-NetFirewallRule -DisplayName $rule.Name -ErrorAction SilentlyContinue | Remove-NetFirewallRule
    New-NetFirewallRule -DisplayName $rule.Name -Direction Inbound -Protocol $rule.Protocol `
        -LocalPort $rule.Port -Action Allow -Profile Any | Out-Null
    Write-Host "Allowed $($rule.Protocol) $($rule.Port)  ($($rule.Name))" -ForegroundColor Green
}

# Also allow the LiveKit program itself (covers any port it uses for audio/video)
$livekit = Join-Path $PSScriptRoot '..\tools\livekit\livekit-server.exe'
if (Test-Path $livekit) {
    $livekit = (Resolve-Path $livekit).Path
    foreach ($protocol in 'TCP', 'UDP') {
        $name = "QRT LiveKit program $protocol"
        Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue | Remove-NetFirewallRule
        New-NetFirewallRule -DisplayName $name -Direction Inbound -Program $livekit `
            -Protocol $protocol -Action Allow -Profile Any | Out-Null
    }
    Write-Host "Allowed the LiveKit program ($livekit)" -ForegroundColor Green
}

# Windows blocks more on "Public" networks
$network = Get-NetConnectionProfile -ErrorAction SilentlyContinue | Where-Object { $_.IPv4Connectivity -ne 'Disconnected' } | Select-Object -First 1
if ($network -and $network.NetworkCategory -eq 'Public') {
    Write-Host ''
    Write-Host "Your network '$($network.Name)' is set to Public." -ForegroundColor Yellow
    Write-Host 'If calls still fail, set it to Private: Settings > Network & internet > Wi-Fi > (your network) > Private network.'
}

Write-Host ''
Write-Host 'Done. Restart start-server.bat before testing calls again.'
