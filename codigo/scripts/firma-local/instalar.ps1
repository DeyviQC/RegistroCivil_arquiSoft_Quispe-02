param([switch]$Instalar)
$ErrorActionPreference = 'Stop'
$taskShortcut = Join-Path $env:APPDATA 'Microsoft/Windows/Start Menu/Programs/PCM/Plataforma Firma Perú/Plataforma Firma Perú.appref-ms'
if (!(Test-Path -LiteralPath $taskShortcut)) { throw 'No se encuentra el acceso directo instalado de Firma Perú.' }
if (![IO.File]::ReadAllText($taskShortcut).StartsWith('https://resources.firmaperu.gob.pe/app/clickonce/cliente/FirmaPeru.application#')) { throw 'El acceso directo no corresponde a Firma Perú.' }
$taskRunner = Join-Path $env:WINDIR 'System32/rundll32.exe'
$taskShim = Join-Path $env:WINDIR 'System32/dfshim.dll'
if (!(Test-Path -LiteralPath $taskRunner) -or !(Test-Path -LiteralPath $taskShim)) { throw 'No se encuentra el iniciador ClickOnce de Windows.' }
# Same handler registered by Windows for .appref-ms; arguments are fixed, never from the URL.
$taskPreviousCommand = '"'+$taskRunner+'" "'+$taskShim+'",ShOpenVerbShortcut "'+$taskShortcut+'"|'
$taskCommand = '"'+$taskRunner+'" dfshim.dll,ShOpenVerbShortcut '+$taskShortcut+'|'
if (!$Instalar) { Write-Output 'Acceso directo oficial comprobado. No se necesita compilar ni instalar un asistente.'; return }
$taskKey = 'HKCU:/Software/Classes/rcmunicipal-firma'
if (Test-Path "$taskKey/shell/open/command") {
    $taskExisting = (Get-ItemProperty "$taskKey/shell/open/command").'(default)'
    if ($taskExisting -and $taskExisting -ne $taskCommand -and $taskExisting -ne $taskPreviousCommand -and $taskExisting -notmatch '\\RegistroCivilFirma\\(RegistroCivilFirma|AbrirFirmaPeru)\.exe" "%1"$') { throw 'El protocolo pertenece a otra aplicación.' }
}
New-Item -Path "$taskKey/shell/open/command" -Force | Out-Null
Set-Item -Path $taskKey -Value 'URL:Registro Civil - Abrir Firma Perú'
New-ItemProperty -Path $taskKey -Name 'URL Protocol' -Value '' -PropertyType String -Force | Out-Null
Set-Item -Path "$taskKey/shell/open/command" -Value $taskCommand
if ((Get-ItemProperty "$taskKey/shell/open/command").'(default)' -ne $taskCommand) { throw 'No se pudo comprobar el acceso.' }
Write-Output 'ACCESO DIRECTO ACTUALIZADO. Abre Firma Perú mediante Windows; ya no usa el asistente.'