# Returns this PC's IPv4 address on the local network (the address phones on
# the same Wi-Fi use to reach it). Prefers the Wi-Fi adapter, then any other
# connected adapter with a gateway, so VPN or virtual adapters aren't picked.
$configs = Get-NetIPConfiguration -ErrorAction SilentlyContinue |
    Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' }

$preferred = $configs | Where-Object { $_.InterfaceAlias -match 'Wi-?Fi|WLAN|Wireless' } | Select-Object -First 1
if (-not $preferred) {
    $preferred = $configs | Select-Object -First 1
}

if ($preferred) {
    $ip = $preferred.IPv4Address |
        Where-Object { $_.IPAddress -notlike '169.254.*' } |
        Select-Object -First 1 -ExpandProperty IPAddress
    if ($ip) { return $ip }
}

return $null
