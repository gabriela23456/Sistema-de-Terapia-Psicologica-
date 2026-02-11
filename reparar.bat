@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo ============================================
echo   CenTI-R - Reparar instalación
echo ============================================
echo.
echo IMPORTANTE: Cierra Cursor y cualquier ventana
echo que tenga abierta esta carpeta antes de continuar.
echo.
pause

echo Eliminando node_modules...
if exist node_modules (
    rmdir /s /q node_modules
    echo node_modules eliminado.
) else (
    echo No existe node_modules.
)

echo.
echo Eliminando package-lock.json...
if exist package-lock.json del package-lock.json

echo.
echo Instalando dependencias de nuevo...
call npm install

echo.
if errorlevel 1 (
    echo ERROR. Prueba:
    echo 1. Cerrar Cursor completamente
    echo 2. Ejecutar este script como Administrador
    echo 3. Desactivar temporalmente el antivirus
) else (
    echo Instalación correcta. Ejecuta .\iniciar.bat
)

echo.
pause
