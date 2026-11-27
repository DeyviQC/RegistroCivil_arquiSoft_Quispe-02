@echo off
setlocal
title Detener HTTPS temporal
powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File "%~dp0scripts\tunel\detener.ps1"
pause
