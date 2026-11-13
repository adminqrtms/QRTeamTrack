# Prints the path of ngrok.exe: tools\ngrok\ngrok.exe, or ngrok installed on the PATH.
$local = Join-Path $PSScriptRoot '..\tools\ngrok\ngrok.exe'
if (Test-Path $local) { return (Resolve-Path $local).Path }
$cmd = Get-Command ngrok -ErrorAction SilentlyContinue
if ($cmd) { return $cmd.Source }
return $null
