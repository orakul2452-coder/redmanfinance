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

$smtpHostInput = Read-Host 'SMTP host [smtp.gmail.com]'
$env:SMTP_HOST = if ([string]::IsNullOrWhiteSpace($smtpHostInput)) { 'smtp.gmail.com' } else { $smtpHostInput.Trim() }
$smtpPortInput = Read-Host 'SMTP port [465]'
$env:SMTP_PORT = if ([string]::IsNullOrWhiteSpace($smtpPortInput)) { '465' } else { $smtpPortInput.Trim() }
if ($env:SMTP_PORT -notin @('465', '587')) {
    $env:DB_PASSWORD = ''
    throw 'SMTP port must be 465 or 587.'
}

$env:SMTP_USERNAME = Read-Host 'Gmail sender address'
if ($env:SMTP_USERNAME -notmatch '^[^\s@]+@[^\s@]+\.[^\s@]+$') {
    $env:DB_PASSWORD = ''
    throw 'Enter a valid Gmail sender address.'
}
$smtpSecurePassword = Read-Host 'Google App Password (hidden; do not paste into chat)' -AsSecureString
$smtpPasswordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($smtpSecurePassword)
try {
    $env:SMTP_PASSWORD = ([Runtime.InteropServices.Marshal]::PtrToStringBSTR($smtpPasswordPointer) -replace '\s', '')
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($smtpPasswordPointer)
}
$env:MAIL_FROM_ADDRESS = $env:SMTP_USERNAME
$env:MAIL_FROM_NAME = 'Redman Finance'
$env:MAKE_FINANCE_ALERT_WEBHOOK_URL = Read-Host 'Make finance-alert webhook URL (optional; press Enter to disable)'
$secureMakeApiKey = Read-Host 'Make webhook API key (optional; press Enter if not configured)' -AsSecureString
$makeApiKeyPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureMakeApiKey)
try {
    $env:MAKE_FINANCE_ALERT_WEBHOOK_API_KEY = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($makeApiKeyPointer)
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($makeApiKeyPointer)
}

try {
    Set-Location $projectRoot
    if ($SchemaOnly) {
        & $phpExe -d "extension_dir=$phpRoot\ext" -d extension=mysqli -d extension=openssl (Join-Path $projectRoot 'import-schema.php') -- --schema-only
        if ($LASTEXITCODE -ne 0) {
            exit $LASTEXITCODE
        }
    }

    & $phpExe -d "extension_dir=$phpRoot\ext" -d extension=mysqli -d extension=openssl -S 127.0.0.1:8000 -t $projectRoot
}
finally {
    $env:DB_PASSWORD = ''
    $env:SMTP_PASSWORD = ''
    $env:SMTP_USERNAME = ''
    $env:MAIL_FROM_ADDRESS = ''
    $env:MAIL_FROM_NAME = ''
    $env:SMTP_HOST = ''
    $env:SMTP_PORT = ''
    $env:MAKE_FINANCE_ALERT_WEBHOOK_URL = ''
    $env:MAKE_FINANCE_ALERT_WEBHOOK_API_KEY = ''
}