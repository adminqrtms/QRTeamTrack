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

# Version: the next build number after the newest published update (1.0.2, 1.0.3, ...)
Set-Location (Join-Path $root 'web\qrt')
$code = ((& php artisan qrt:app-next-version) | Select-Object -Last 1)
if ($LASTEXITCODE -ne 0 -or "$code" -notmatch '^\d+$') {
    Write-Host 'Could not read the app version from the server database.' -ForegroundColor Red
    Write-Host 'Make sure MySQL is running in XAMPP and run update.bat, then try again.'
    exit 1
}
$code = [int]$code
$versionName = "1.0.$code"
Write-Host "  Version $versionName (build $code)"
Write-Host '  This takes a few minutes the first time.'
Write-Host ''

Set-Location (Join-Path $root 'mobile\mobile_qrtms')
& flutter pub get
if ($LASTEXITCODE -ne 0) { Write-Host 'flutter pub get failed.' -ForegroundColor Red; exit 1 }

& flutter build apk --release --build-number $code --build-name $versionName `
    "--dart-define=API_URL=$apiUrl" "--dart-define=APP_BUILD=$code" "--dart-define=APP_VERSION=$versionName"
$buildOk = $LASTEXITCODE -eq 0
git checkout -- analysis_options.yaml 2>$null
if (-not $buildOk) { Write-Host 'The build failed (see the messages above).' -ForegroundColor Red; exit 1 }

$apk = Join-Path $root 'QRTeamTrack.apk'
Copy-Item 'build\app\outputs\flutter-apk\app-release.apk' $apk -Force
$sizeMb = [math]::Round((Get-Item $apk).Length / 1MB, 1)

Write-Host ''
Write-Host "  Done: $apk (version $versionName, $sizeMb MB)" -ForegroundColor Green
Write-Host ''

# In-app update: phones with the app installed get "Update available"
$publish = Read-Host 'Publish this as an update for phones that already have the app? (y/N)'
if ($publish -match '^[yY]') {
    Write-Host ''
    Write-Host "Type what's new, one change per line. Press Enter on an empty line to finish."
    $lines = @()
    while ($true) {
        $line = Read-Host '  -'
        if (-not $line) { break }
        $lines += $line
    }
    $required = Read-Host 'Must everyone install it before using the app (required)? (y/N)'

    $notesFile = Join-Path $env:TEMP 'qrt-release-notes.txt'
    [System.IO.File]::WriteAllLines($notesFile, [string[]]$lines, (New-Object System.Text.UTF8Encoding($false)))

    $publishArgs = @('artisan', 'qrt:app-publish', $apk, "--code=$code", "--name=$versionName", "--notes-file=$notesFile")
    if ($required -match '^[yY]') { $publishArgs += '--required' }
    Set-Location (Join-Path $root 'web\qrt')
    & php @publishArgs
    $published = $LASTEXITCODE -eq 0
    Remove-Item $notesFile -ErrorAction SilentlyContinue
    if ($published) {
        Write-Host '  Phones will show "Update available" the next time they open the app.' -ForegroundColor Green
        Write-Host '  You can edit or delete it in the web panel: App Updates.'
    } else {
        Write-Host '  Publishing failed (see above).' -ForegroundColor Red
    }
} else {
    Write-Host '  Not published. To install it, send the file to the phones (Messenger, Google Drive, USB):'
    Write-Host '    tap the file > allow "Install unknown apps" if asked > Install.'
}
Write-Host ''
Write-Host '  The server (start-server.bat, with ngrok) must be running on this PC.'
