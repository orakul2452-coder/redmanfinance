$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$phpRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe'
$phpExe = Join-Path $phpRoot 'php.exe'

if (-not (Test-Path $phpExe)) {
    throw "PHP executable not found: $phpExe"
}

$smtpUsername = Read-Host 'Gmail sender address'
if ($smtpUsername -notmatch '^[^\s@]+@[^\s@]+\.[^\s@]+$') {
    throw 'Enter a valid Gmail sender address.'
}

$smtpSecurePassword = Read-Host 'Google App Password (hidden; do not paste into chat)' -AsSecureString
$smtpPasswordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($smtpSecurePassword)
try {
    $env:SMTP_HOST = 'smtp.gmail.com'
    $env:SMTP_PORT = '465'
    $env:SMTP_USERNAME = $smtpUsername
    $env:SMTP_PASSWORD = ([Runtime.InteropServices.Marshal]::PtrToStringBSTR($smtpPasswordPointer) -replace '\s', '')
    $env:MAIL_FROM_ADDRESS = $smtpUsername
    $env:MAIL_FROM_NAME = 'Redman Finance'
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($smtpPasswordPointer)
}

$testRecipient = Read-Host 'Test recipient email address'
if ($testRecipient -notmatch '^[^\s@]+@[^\s@]+\.[^\s@]+$') {
    $env:SMTP_PASSWORD = ''
    throw 'Enter a valid test recipient email address.'
}

try {
    Push-Location $projectRoot
    & $phpExe -d "extension_dir=$phpRoot\ext" -d extension=mysqli -d extension=openssl tools\test_smtp.php $testRecipient
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}
finally {
    Pop-Location
    $env:SMTP_PASSWORD = ''
    $env:SMTP_USERNAME = ''
    $env:MAIL_FROM_ADDRESS = ''
    $env:MAIL_FROM_NAME = ''
    $env:SMTP_HOST = ''
    $env:SMTP_PORT = ''
}