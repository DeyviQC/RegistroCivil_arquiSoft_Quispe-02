# Registra en el Programador de tareas de Windows una tarea que ejecuta
# "php artisan schedule:run" cada minuto, sin abrir ventanas, para el usuario actual
# (no requiere administrador). Así corren el respaldo, los vencimientos y la cola de correos.
#
# Uso:   powershell -ExecutionPolicy Bypass -File scripts\windows\registrar-tareas.ps1 [-Php C:\ruta\php.exe]
# Quitar: Unregister-ScheduledTask -TaskName "RegistroCivil-Tareas" -Confirm:$false
param(
    [string]$Php = $(if (Get-Command php -ErrorAction SilentlyContinue) { (Get-Command php).Source } else { "$env:USERPROFILE\.config\herd\bin\php84\php.exe" }),
    [string]$Nombre = 'RegistroCivil-Tareas'
)
$ErrorActionPreference = 'Stop'
$proyecto = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if (-not (Test-Path $Php)) { throw "No se encontró PHP en '$Php'. Indica la ruta con -Php." }

# Lanzador VBS: ejecuta el comando con la ventana oculta (0) y sin esperar.
$vbs = Join-Path $PSScriptRoot 'schedule-run.vbs'
@"
Set sh = CreateObject("WScript.Shell")
sh.CurrentDirectory = "$proyecto"
sh.Run """$Php"" artisan schedule:run", 0, False
"@ | Set-Content -Path $vbs -Encoding ASCII

$accion = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument "`"$vbs`"" -WorkingDirectory $proyecto
$disparador = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
$ajustes = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30)
Register-ScheduledTask -TaskName $Nombre -Action $accion -Trigger $disparador -Settings $ajustes -Description 'Registro Civil: php artisan schedule:run cada minuto' -Force | Out-Null
Write-Host "Tarea '$Nombre' registrada: ejecuta schedule:run cada minuto en $proyecto"
