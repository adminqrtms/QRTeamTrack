# Prints the saved ngrok domain (tools\ngrok\domain.txt), or nothing if ngrok isn't set up.
$file = Join-Path $PSScriptRoot '..\tools\ngrok\domain.txt'
if (-not (Test-Path $file)) { return '' }
return (Get-Content $file -Raw).Trim()
