@echo off
cd /d "%~dp0public"
echo Registro Civil - version de desarrollo
echo URL: http://127.0.0.1:8001
 echo Mantenga DBngin con MySQL iniciado.
echo Mantenga esta ventana abierta mientras usa desarrollo.
"C:\Users\LOQ\.config\herd\bin\php84\php.exe" -S 127.0.0.1:8001 -t . ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
pause