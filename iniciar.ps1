# CenTI-R - Script de inicialización (PowerShell)
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  CenTI-R - Inicialización" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "[1/3] Instalando dependencias npm..." -ForegroundColor Yellow
npm install
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error al instalar. Ejecuta: .\reparar.bat" -ForegroundColor Red
    Write-Host "(Cierra Cursor antes de reparar)" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "[2/3] Creando datos de prueba..." -ForegroundColor Yellow
php api/seed.php

Write-Host ""
Write-Host "[3/3] Iniciando servidores..." -ForegroundColor Yellow
Write-Host ""
Write-Host "  API PHP:  http://localhost:8080" -ForegroundColor Green
Write-Host "  Frontend: http://localhost:4321" -ForegroundColor Green
Write-Host ""
Write-Host "Abre http://localhost:4321 en tu navegador" -ForegroundColor White
Write-Host "Presiona Ctrl+C para detener" -ForegroundColor Gray
Write-Host "============================================" -ForegroundColor Cyan

Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PSScriptRoot'; php -S localhost:8080 -t api api/router.php"
Start-Sleep -Seconds 2
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PSScriptRoot'; npm run dev"

Write-Host ""
Write-Host "Servidores iniciados." -ForegroundColor Green
