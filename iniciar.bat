@echo off
chcp 65001 >nul
echo ============================================
echo   CenTI-R - Inicialización
echo ============================================
echo.

cd /d "%~dp0"

echo [1/3] Instalando dependencias npm...
call npm install
if errorlevel 1 (
    echo Error al instalar dependencias.
    pause
    exit /b 1
)

echo.
echo [2/3] Creando datos de prueba...
php api/seed.php

echo.
echo [3/3] Iniciando servidores...
echo.
echo   API PHP:  http://localhost:8080
echo   Frontend: http://localhost:4321
echo.
echo Abre http://localhost:4321 en tu navegador
echo Presiona Ctrl+C para detener los servidores
echo ============================================

start "CenTI-R API" cmd /k "php -S localhost:8080 -t api api/router.php"
timeout /t 2 /nobreak >nul
start "CenTI-R Frontend" cmd /k "npm run dev"

echo.
echo Servidores iniciados en ventanas separadas.
pause
