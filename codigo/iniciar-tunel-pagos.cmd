@echo off
setlocal
title Registro Civil - HTTPS temporal de pruebas
echo Iniciando HTTPS temporal para el portal ciudadano y Mercado Pago.
echo Mantenga MySQL en DBngin y la computadora encendidos.
echo.
powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File "%~dp0scripts\tunel\iniciar.ps1"
if errorlevel 1 (
    echo.
    echo No se pudo conectar el tunel. Envie una captura de este error.
    pause
    exit /b 1
)
echo.
echo Copie la direccion Webhook en Mercado Pago, en modo de pruebas.
echo Puede cerrar esta ventana. Para apagar el enlace use detener-tunel-pagos.cmd.
pause
