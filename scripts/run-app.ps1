# Runs the Flutter app pointed at this PC's server, using the current IP.
# Usage: run-app.bat [device]
#   run-app.bat             choose from the connected devices
#   run-app.bat chrome      run in Chrome
#   run-app.bat 22101316G   run on a specific phone
#   run-app.bat emulator    start the Android emulator (if needed) and run on it
#   run-app.bat 22101316G 192.168.1.15   use this IP instead of detecting it
param([string]$Device = '', [string]$ServerIp = '')

$ip = $ServerIp
if (-not $ip) {
    $ip = & (Join-Path $PSScriptRoot 'lan-ip.ps1')
}
if (-not $ip) {
    Write-Host 'Could not detect this PC''s IP address.' -ForegroundColor Yellow
    $ip = Read-Host 'Type the Wi-Fi IPv4 Address from ipconfig'
}

$apiUrl = "http://${ip}:8000/api"
Write-Host ''
Write-Host "  The app will use the server at $apiUrl" -ForegroundColor Green
Write-Host "  (Check it matches the Wi-Fi IPv4 Address in ipconfig. If not, run:"
Write-Host "   run-app.bat <device> <your-ip>)"
Write-Host ''

Set-Location (Join-Path $PSScriptRoot '..\mobile\mobile_qrtms')

function Get-RunningEmulatorId {
    $json = (& flutter devices --machine 2>$null) -join "`n"
    try {
        $devices = $json | ConvertFrom-Json
    } catch {
        return $null
    }
    $emulator = $devices | Where-Object { $_.emulator -and $_.targetPlatform -like 'android*' } | Select-Object -First 1
    if ($emulator) { return $emulator.id }
    return $null
}

if ($Device -eq 'emulator') {
    $running = Get-RunningEmulatorId
    if ($running) {
        $Device = $running
    } else {
        # Rows look like: Pixel_7_API_34 • Pixel 7 API 34 • Google • android
        $emulatorIds = & flutter emulators 2>$null |
            Where-Object { $_ -match 'android\s*$' -and $_ -notmatch '^\s*Id\b' } |
            ForEach-Object { ($_.Trim() -split '\s+')[0] }

        if (-not $emulatorIds) {
            Write-Host 'No Android emulator found. Create one in Android Studio > Device Manager first.' -ForegroundColor Red
            exit 1
        }

        $emulatorId = @($emulatorIds)[0]
        Write-Host "  Starting emulator $emulatorId (this can take a minute)..."
        & flutter emulators --launch $emulatorId | Out-Null

        $deadline = (Get-Date).AddMinutes(3)
        do {
            Start-Sleep -Seconds 5
            $running = Get-RunningEmulatorId
        } until ($running -or (Get-Date) -gt $deadline)

        if (-not $running) {
            Write-Host 'The emulator did not start in time. Wait for its home screen, then run: run-app.bat emulator' -ForegroundColor Red
            exit 1
        }
        $Device = $running
    }
    Write-Host "  Using emulator $Device"
    Write-Host ''
}

$flutterArgs = @('run', "--dart-define=API_URL=$apiUrl")
if ($Device) {
    $flutterArgs += @('-d', $Device)
}

# Without a device, Flutter lists the connected ones and asks which to use.
& flutter @flutterArgs
