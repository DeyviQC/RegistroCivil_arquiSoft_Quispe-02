$ErrorActionPreference = 'Stop'
$taskRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$taskRuntime = Join-Path $taskRoot 'tmp/tunel'
$taskStartLock = $null
try { $taskStartLock = [IO.File]::Open((Join-Path $taskRuntime 'inicio.lock'),[IO.FileMode]::OpenOrCreate,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None) }
catch [IO.IOException] { throw 'El túnel ya se está iniciando en otra ventana. Espere a que termine esa ventana.' }
try {
$taskCloudflared = Join-Path $taskRuntime 'cloudflared.exe'
$taskMetadata = Get-Content -LiteralPath (Join-Path $taskRuntime 'cloudflared-oficial.json') -Raw | ConvertFrom-Json
if ((Get-FileHash -LiteralPath $taskCloudflared -Algorithm SHA256).Hash.ToLowerInvariant() -ne $taskMetadata.sha256) { throw 'El ejecutable no coincide con la descarga verificada.' }
$taskStateFile = Join-Path $taskRuntime 'activo.json'
if (Test-Path -LiteralPath $taskStateFile) {
    $taskOld = Get-Content -LiteralPath $taskStateFile -Raw | ConvertFrom-Json
    $taskHealthy = $false
    if ($taskOld.url -match '^https://[a-z0-9-]+\.trycloudflare\.com$' -and $taskOld.metrics_port) {
        try {
            $taskReadyResponse = Invoke-WebRequest -UseBasicParsing -Uri ('http://127.0.0.1:'+$taskOld.metrics_port+'/ready') -TimeoutSec 3
            $taskHealthy = $taskReadyResponse.StatusCode -eq 200
            foreach ($taskKind in @('origin','tunnel')) {
                $taskExistingProcess = Get-Process -Id $taskOld.($taskKind+'_pid') -ErrorAction Stop
                $taskExpected = if ($taskKind -eq 'origin') { 'C:\Users\LOQ\.config\herd\bin\php84\php.exe' } else { $taskCloudflared }
                if ($taskExistingProcess.StartTime.ToUniversalTime().ToString('o') -ne $taskOld.($taskKind+'_start') -or ![string]::Equals($taskExistingProcess.Path,$taskExpected,[StringComparison]::OrdinalIgnoreCase)) { $taskHealthy = $false }
            }
        } catch { $taskHealthy = $false }
    }
    if ($taskHealthy) {
        Write-Output ('Portal: '+$taskOld.url+'/tramite')
        Write-Output ('Webhook: '+$taskOld.url+'/pagos/mercadopago/notificacion')
        Write-Output 'El túnel ya está conectado; se conserva la misma dirección.'
        return
    }
    # Only verified processes from this workspace can be retired by detener.ps1.
    & (Join-Path $PSScriptRoot 'detener.ps1')
}
function Get-FreeTunnelPort([int]$First) {
    foreach ($taskCandidate in $First..($First+20)) {
        $taskProbe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$taskCandidate)
        try { $taskProbe.Start(); return $taskCandidate }
        catch [Net.Sockets.SocketException] { }
        finally { $taskProbe.Stop() }
    }
    throw 'No se encontró un puerto libre para las pruebas.'
}
$taskPort = Get-FreeTunnelPort 8002
$taskMetricsPort = Get-FreeTunnelPort 20242
$taskPhp = 'C:/Users/LOQ/.config/herd/bin/php84/php.exe'
$taskServer = $null; $taskTunnel = $null
$taskUrl = ''
$taskEnvFile = Join-Path $taskRoot '.env'
$taskEnvText = [IO.File]::ReadAllText($taskEnvFile)
$taskPreviousUrl = ''
if ($taskEnvText -match '(?m)^MERCADOPAGO_PUBLIC_URL=([^\r\n]*)') { $taskPreviousUrl = $Matches[1] }
function Save-TunnelState {
    $taskState = @{origin_pid=$taskServer.Id;origin_port=$taskPort;metrics_port=$taskMetricsPort;origin_start=$taskServer.StartTime.ToUniversalTime().ToString('o');url=$taskUrl;previous_url=$taskPreviousUrl;tunnel_pid=0;tunnel_start=''}
    if ($taskTunnel) { $taskState.tunnel_pid=$taskTunnel.Id; $taskState.tunnel_start=$taskTunnel.StartTime.ToUniversalTime().ToString('o') }
    [IO.File]::WriteAllText($taskStateFile, ($taskState|ConvertTo-Json), [Text.UTF8Encoding]::new($false))
}
try {
    $taskRouter = Join-Path $PSScriptRoot 'router.php'
    $taskServer = Start-Process -FilePath $taskPhp -ArgumentList @('-d','display_errors=0','-S',"127.0.0.1:$taskPort",'-t',('"'+(Join-Path $taskRoot 'public')+'"'),('"'+$taskRouter+'"')) -WorkingDirectory $taskRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskRuntime 'origen.out.log') -RedirectStandardError (Join-Path $taskRuntime 'origen.error.log')
    Save-TunnelState
    function Get-TunnelStatus([string]$Path) {
        $taskRequest = [Net.HttpWebRequest]::Create("http://127.0.0.1:$taskPort"+$Path)
        $taskRequest.AllowAutoRedirect=$false; $taskRequest.Proxy=$null; $taskRequest.Timeout=10000
        try { $taskResponse=$taskRequest.GetResponse() }
        catch [Net.WebException] { $taskResponse=$_.Exception.Response; if(!$taskResponse) { throw } }
        try { return [int]$taskResponse.StatusCode } finally { $taskResponse.Dispose() }
    }
    Start-Sleep -Milliseconds 700
    if ($taskServer.HasExited) { throw 'No se pudo iniciar el servidor de pruebas.' }
    foreach ($taskDenied in @('/login','/.env','/usuarios','/atencion-ciudadana','/img/PROPEUSTA%20REGISTRO%20CIVIL.pdf')) {
        if ((Get-TunnelStatus $taskDenied) -ne 404) { throw 'El origen no está restringiendo las rutas internas.' }
    }
    if ((Get-TunnelStatus '/tramite') -ne 200 -or (Get-TunnelStatus '/js/municipal-interfaz.js') -ne 200) { throw 'El portal de pruebas no responde correctamente. Compruebe MySQL.' }
    $taskTunnelArgs = @('tunnel','--no-autoupdate','--url',"http://127.0.0.1:$taskPort",'--protocol','quic','--metrics',"127.0.0.1:$taskMetricsPort")
    $taskTunnel = Start-Process -FilePath $taskCloudflared -ArgumentList $taskTunnelArgs -WorkingDirectory $taskRuntime -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskRuntime 'cloudflared.out.log') -RedirectStandardError (Join-Path $taskRuntime 'cloudflared.error.log')
    Save-TunnelState
    $taskUrl = $null
    for ($taskAttempt=0; $taskAttempt -lt 45; $taskAttempt++) {
        Start-Sleep -Milliseconds 500
        if ($taskTunnel.HasExited) { throw 'Cloudflare no pudo iniciar. Revise tmp/tunel/cloudflared.error.log.' }
        $taskLog = Get-Content -LiteralPath (Join-Path $taskRuntime 'cloudflared.error.log') -Raw -ErrorAction SilentlyContinue
        if ($taskLog -match 'https://[a-z0-9-]+\.trycloudflare\.com') { $taskUrl = $Matches[0]; break }
    }
    if (!$taskUrl) { throw 'No se obtuvo la dirección HTTPS temporal.' }
    $taskReady = $false
    for ($taskAttempt=0; $taskAttempt -lt 60; $taskAttempt++) {
        $taskLog = Get-Content -LiteralPath (Join-Path $taskRuntime 'cloudflared.error.log') -Raw -ErrorAction SilentlyContinue
        if ($taskLog -match 'Registered tunnel connection') { $taskReady = $true; break }
        if ($taskTunnel.HasExited) { break }
        Start-Sleep -Milliseconds 500
    }
    if (!$taskReady) { throw 'Cloudflare no estableció la conexión. No se ha activado ninguna URL en el sistema.' }
    # Update just this development setting, preserving secrets and the local APP_URL.
    $taskEnvText = [IO.File]::ReadAllText($taskEnvFile)
    Save-TunnelState
    $taskEnvText = [regex]::Replace($taskEnvText, '(?m)^MERCADOPAGO_PUBLIC_URL=[^\r\n]*', 'MERCADOPAGO_PUBLIC_URL='+$taskUrl)
    [IO.File]::WriteAllText($taskEnvFile, $taskEnvText, [Text.UTF8Encoding]::new($false))
    & $taskPhp (Join-Path $taskRoot 'artisan') config:clear | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo recargar la configuración.' }
    Write-Output ('Portal: '+$taskUrl+'/tramite')
    Write-Output ('Webhook: '+$taskUrl+'/pagos/mercadopago/notificacion')
    Write-Output 'Túnel temporal iniciado. Para cerrarlo, ejecute detener.ps1.'
} catch {
    if ($taskTunnel -and !$taskTunnel.HasExited) { $taskTunnel.Kill() }
    if ($taskServer -and !$taskServer.HasExited) { $taskServer.Kill() }
    if (Test-Path -LiteralPath $taskStateFile) { Remove-Item -LiteralPath $taskStateFile }
    throw
}
} finally { $taskStartLock.Dispose() }
