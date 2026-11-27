$ErrorActionPreference = 'Stop'
$taskRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$taskFile = Join-Path $taskRoot 'tmp/tunel/activo.json'
if (!(Test-Path -LiteralPath $taskFile)) { Write-Output 'No hay un túnel registrado.'; return }
$taskState = Get-Content -LiteralPath $taskFile -Raw | ConvertFrom-Json
foreach ($taskKind in @('origin','tunnel')) {
    $taskPid = $taskState.($taskKind+'_pid')
    if (!$taskPid -or $taskPid -le 0) { continue }
    $taskProcess = Get-Process -Id $taskPid -ErrorAction SilentlyContinue
    if (!$taskProcess) { continue }
    if ($taskProcess.StartTime.ToUniversalTime().ToString('o') -ne $taskState.($taskKind+'_start')) { throw 'El PID fue reutilizado; no se detendrá otro proceso.' }
    $taskExpected = if ($taskKind -eq 'origin') { [IO.Path]::GetFullPath('C:/Users/LOQ/.config/herd/bin/php84/php.exe') } else { Join-Path $taskRoot 'tmp/tunel/cloudflared.exe' }
    if (![string]::Equals($taskProcess.Path,$taskExpected,[StringComparison]::OrdinalIgnoreCase)) { throw 'El ejecutable no corresponde al túnel.' }
    Stop-Process -Id $taskPid
}
$taskEnv = Join-Path $taskRoot '.env'
$taskText = [IO.File]::ReadAllText($taskEnv)
$taskText = [regex]::Replace($taskText, '(?m)^MERCADOPAGO_PUBLIC_URL='+[regex]::Escape($taskState.url)+'\r?$', { 'MERCADOPAGO_PUBLIC_URL='+$taskState.previous_url })
[IO.File]::WriteAllText($taskEnv,$taskText,[Text.UTF8Encoding]::new($false))
& 'C:/Users/LOQ/.config/herd/bin/php84/php.exe' (Join-Path $taskRoot 'artisan') config:clear | Out-Null
Remove-Item -LiteralPath $taskFile
Write-Output 'Túnel detenido. El servidor local 8001 continúa disponible.'
