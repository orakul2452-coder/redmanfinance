param([switch]$SchemaOnly)

$ErrorActionPreference = 'Stop'

$phpRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe'
$phpExe = Join-Path $phpRoot 'php.exe'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path

if (-not (Test-Path $phpExe)) {
    throw "PHP executable not found: $phpExe"
}

$env:DB_HOST = Read-Host 'Database host'
$env:DB_PORT = Read-Host 'Database port'
$env:DB_USER = Read-Host 'Database username'
$env:DB_NAME = Read-Host 'Database name'
$env:DB_SSL_CA = Read-Host 'Path to the database CA certificate'
$securePassword = Read-Host 'Database password (input is hidden)' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)

try {
    $env:DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}

Set-Location $projectRoot

if ($SchemaOnly) {
    & $phpExe -d "extension_dir=$phpRoot\ext" -d extension=mysqli -d extension=openssl (Join-Path $projectRoot 'import-schema.php') -- --schema-only
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

& $phpExe -d "extension_dir=$phpRoot\ext" -d extension=mysqli -d extension=openssl -S 127.0.0.1:8000 -t $projectRoot