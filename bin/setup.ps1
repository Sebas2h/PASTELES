$ErrorActionPreference = "Stop"
Write-Host "=== Pasteleria Dulce Naranja ===" -ForegroundColor Cyan
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw "PHP no esta disponible en PATH." }
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env"; Write-Host "Se creo .env. Editalo con tus credenciales de MariaDB." -ForegroundColor Yellow }
php bin/generate-install-key.php
Write-Host "1) Importa database/schema.sql en MariaDB" -ForegroundColor Green
Write-Host "2) Ejecuta: php -S localhost:8080 -t public public/router.php" -ForegroundColor Green
Write-Host "3) Abre http://localhost:8080/setup-admin" -ForegroundColor Green
