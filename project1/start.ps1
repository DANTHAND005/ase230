param(
    [string]$DatabaseUser = 'aquarium_project1',
    [int]$DatabasePort = 3306
)
$ErrorActionPreference = 'Stop'
$php = if (Get-Command php -ErrorAction SilentlyContinue) { 'php' } else { 'C:\Xampp\php\php.exe' }
$env:DB_USER = $DatabaseUser
$env:DB_PORT = "$DatabasePort"
$env:DB_PASSWORD = [System.Net.NetworkCredential]::new('', (Read-Host 'Database password' -AsSecureString)).Password
$env:API_TOKEN = [System.Net.NetworkCredential]::new('', (Read-Host 'Choose the API Bearer token' -AsSecureString)).Password
Push-Location $PSScriptRoot
try {
    & $php -S localhost:8080 -t code code/router.php
} finally {
    Pop-Location
    Remove-Item Env:DB_PASSWORD, Env:API_TOKEN
}
