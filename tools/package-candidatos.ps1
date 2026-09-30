$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$output = Join-Path $root 'artifacts'
$staging = Join-Path $output ('release-' + [guid]::NewGuid().ToString('N'))
$app = Join-Path $staging 'candidatos'
New-Item -ItemType Directory -Path (Join-Path $app 'migrations') -Force | Out-Null
foreach ($name in @('.htaccess','index.php','core.php','cli.php','style.css')) {
    Copy-Item -LiteralPath (Join-Path $root ('candidatos/' + $name)) -Destination $app
}
Copy-Item -Path (Join-Path $root 'candidatos/migrations/*.sql') -Destination (Join-Path $app 'migrations')
# ZipFile includes .htaccess; Compress-Archive can omit hidden files on some platforms.
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = Join-Path $output ('candidatos-code-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
[IO.Compression.ZipFile]::CreateFromDirectory($staging, $zip)
Write-Output $zip
Get-FileHash -LiteralPath $zip -Algorithm SHA256
