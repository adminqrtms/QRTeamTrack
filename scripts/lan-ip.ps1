# Returns this PC's IPv4 address on the network it uses for the internet
# (the address phones on the same Wi-Fi use to reach it).
$route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction SilentlyContinue |
    Sort-Object -Property RouteMetric |
    Select-Object -First 1

if ($route) {
    $ip = Get-NetIPAddress -InterfaceIndex $route.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notlike '169.254.*' } |
        Select-Object -First 1 -ExpandProperty IPAddress
    if ($ip) { return $ip }
}

return $null
