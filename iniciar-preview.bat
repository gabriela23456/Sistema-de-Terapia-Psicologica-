@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo ============================================
echo   CenTI-R - Iniciar con Vista Previa
echo   (Alternativa si "npm run dev" da error)
echo ============================================
echo.

echo [1/3] Compilando proyecto...
echo (Si falla con spawn EPERM, ejecuta este .bat con doble clic fuera de Cursor)
call npm run build
if errorlevel 1 (
    echo.
    echo Error al compilar. Intenta:
    echo 1. Cerrar Cursor y ejecutar este .bat con doble clic
    echo 2. Ejecutar: reparar.bat
    pause
    exit /b 1
)

echo.
echo [2/3] Iniciando API PHP...
start "CenTI-R API" cmd /k "php -S localhost:8080 -t api api/router.php"
timeout /t 2 /nobreak >nul

echo [3/3] Iniciando servidor de vista previa...
start "CenTI-R Preview" cmd /k "npm run preview"

echo.
echo Abre http://localhost:4321 en tu navegador
echo (o el puerto que indique la ventana de preview)
echo ============================================
pause
