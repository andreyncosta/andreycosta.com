$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$output = Join-Path $root 'artifacts'
$staging = Join-Path $output ('release-' + [guid]::NewGuid().ToString('N'))
$app = Join-Path $staging 'candidatos'
New-Item -ItemType Directory -Path (Join-Path $app 'migrations') -Force | Out-Null
foreach ($name in @('.htaccess','index.php','core.php','cli.php','style.css','editorial.css')) {
    Copy-Item -LiteralPath (Join-Path $root ('candidatos/' + $name)) -Destination $app
}
Copy-Item -Path (Join-Path $root 'candidatos/migrations/*.sql') -Destination (Join-Path $app 'migrations')
# ZipFile includes .htaccess; Compress-Archive can omit hidden files on some platforms.
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$zip = Join-Path $output ('candidatos-code-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
$archive = [IO.Compression.ZipFile]::Open($zip, [IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -LiteralPath $staging -Recurse -File -Force | ForEach-Object {
        $entry = $_.FullName.Substring($staging.Length + 1).Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, $entry) | Out-Null
    }
} finally { $archive.Dispose() }
Write-Output $zip
Get-FileHash -LiteralPath $zip -Algorithm SHA256
