@echo off
setlocal
title Registro Civil - Acceso a Firma Peru
echo Registro Civil - Boton para abrir Firma Peru
echo Esta instalacion es para su usuario de Windows. No requiere administrador.
echo.
powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File "%~dp0scripts\firma-local\instalar.ps1" -Instalar
if errorlevel 1 (
    echo.
    echo No se completo la instalacion. Comparta el mensaje de error mostrado arriba.
    pause
    exit /b 1
)
echo.
echo Instalacion completada. Vuelva al sistema y pulse:
echo Abrir Firma Peru.
pause
