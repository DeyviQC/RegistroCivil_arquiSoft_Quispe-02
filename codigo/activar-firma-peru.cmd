@echo off
setlocal
title Activar acceso directo a Firma Peru
echo Actualizando el boton para abrir Firma Peru, sin asistente.
echo.
powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File "%~dp0scripts\firma-local\instalar.ps1" -Instalar
if errorlevel 1 (
    echo No se pudo actualizar. Comparta el error mostrado arriba.
    pause
    exit /b 1
)
echo.
echo Listo. Cierre el aviso viejo, vuelva al sistema y pulse Abrir Firma Peru.
pause