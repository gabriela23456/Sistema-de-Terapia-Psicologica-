@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo Iniciando CenTI-R...
echo.
start "CenTI-R API" cmd /k "php -S localhost:8080 -t api api/router.php"
timeout /t 2 /nobreak >nul
start "CenTI-R Frontend" cmd /k "npm run dev"

echo.
echo Abre http://localhost:4321 en tu navegador
