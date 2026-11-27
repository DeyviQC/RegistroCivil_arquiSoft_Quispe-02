$ErrorActionPreference = 'Stop'
$taskKey = 'HKCU:/Software/Classes/rcmunicipal-firma'
if (Test-Path "$taskKey/shell/open/command") {
    $taskCommand=(Get-ItemProperty "$taskKey/shell/open/command").'(default)'
    $taskShortcut=Join-Path $env:APPDATA 'Microsoft/Windows/Start Menu/Programs/PCM/Plataforma Firma Perú/Plataforma Firma Perú.appref-ms'
    $taskExpected='"'+(Join-Path $env:WINDIR 'System32/rundll32.exe')+'" "'+(Join-Path $env:WINDIR 'System32/dfshim.dll')+'",ShOpenVerbShortcut "'+$taskShortcut+'"|'
    $taskNative='"'+(Join-Path $env:WINDIR 'System32/rundll32.exe')+'" dfshim.dll,ShOpenVerbShortcut '+$taskShortcut+'|'
    if ($taskCommand -ne $taskExpected -and $taskCommand -ne $taskNative -and $taskCommand -notmatch '\\RegistroCivilFirma\\(RegistroCivilFirma|AbrirFirmaPeru)\.exe" "%1"$') { throw 'No se modificará un protocolo ajeno.' }
    Remove-Item -LiteralPath $taskKey -Recurse
}
Write-Output 'Acceso retirado. Firma Perú y todos los PDF se conservan.'