# Levanta un servidor de pruebas de carga parecido a un despliegue real: nginx delante de un grupo de
# procesos php-cgi (FastCGI), usando los binarios que trae Laravel Herd, sin modificar su configuración.
# Herd en Windows atiende con 5 procesos PHP fijos (ver AC03); aquí la cantidad es configurable.
#
# Uso:
#   powershell -File tests\carga\servidor-carga.ps1 -Raiz C:\ruta\codigo -Trabajadores 20 -Puerto 8020
#   powershell -File tests\carga\servidor-carga.ps1 -Detener
param(
    [string]$Raiz = '',
    [int]$Trabajadores = 20,
    [int]$Puerto = 8020,
    [int]$PuertoFastCgi = 9201,
    [switch]$Detener
)
$ErrorActionPreference = 'Stop'
if (-not $Raiz) { $Raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path }
$herd = 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin'
$nginx = Join-Path $herd 'nginx\nginx.exe'
$phpCgi = Join-Path $env:USERPROFILE '.config\herd\bin\php84\php-cgi.exe'
$prefijo = Join-Path $env:TEMP 'registro-civil-carga-nginx'

if ($Detener) {
    if (Test-Path (Join-Path $prefijo 'conf\nginx.conf')) { & $nginx -p "$prefijo\" -s stop 2>$null }
    Get-Content (Join-Path $prefijo 'php-cgi.pids') -ErrorAction SilentlyContinue | ForEach-Object {
        try { Stop-Process -Id ([int]$_) -Force -ErrorAction Stop } catch {}
    }
    Write-Host 'Servidor de carga detenido.'
    exit 0
}

foreach ($ruta in @($nginx, $phpCgi, (Join-Path $Raiz 'public\index.php'))) {
    if (-not (Test-Path $ruta)) { Write-Error "No se encontró $ruta"; exit 2 }
}
foreach ($carpeta in 'conf', 'logs', 'temp') { New-Item -ItemType Directory -Force (Join-Path $prefijo $carpeta) | Out-Null }

# Each php-cgi process serves one request at a time; PHP_FCGI_MAX_REQUESTS=0 keeps it alive instead of
# exiting after 500 requests (the default), which would drop connections in the middle of a test.
$env:PHP_FCGI_MAX_REQUESTS = '0'
$pids = @()
$servidores = @()
for ($i = 0; $i -lt $Trabajadores; $i++) {
    $p = $PuertoFastCgi + $i
    $proc = Start-Process -FilePath $phpCgi -ArgumentList '-b', "127.0.0.1:$p" -WindowStyle Hidden -PassThru
    # Windows 11 treats hidden processes as background work and, on hybrid CPUs (P + E cores), keeps them on
    # the efficiency cores: measured 160 req/s with the P cores at ~40 %. AboveNormal gives 376 req/s.
    $proc.PriorityClass = 'AboveNormal'
    $pids += $proc.Id
    $servidores += "        server 127.0.0.1:$p;"
}
$pids | Out-File -Encoding ascii (Join-Path $prefijo 'php-cgi.pids')

$publico = (Join-Path $Raiz 'public').Replace('\', '/')
$fastcgi = (Join-Path $herd 'nginx\conf\fastcgi_params').Replace('\', '/')
if (-not (Test-Path $fastcgi)) { $fastcgi = (Join-Path $env:USERPROFILE '.config\herd\config\nginx\fastcgi_params').Replace('\', '/') }
@"
worker_processes 1;
error_log logs/error.log warn;
events { worker_connections 4096; multi_accept on; }
http {
    access_log off;
    # Without these two lines nginx on Windows served only ~160 static files per second (measured);
    # with them, ~1 400 per second.
    open_file_cache max=1000 inactive=60s;
    sendfile on;
    keepalive_timeout 5;
    client_max_body_size 20M;
    upstream php {
        least_conn;
$($servidores -join "`n")
    }
    server {
        listen 127.0.0.1:$Puerto;
        root "$publico";
        index index.php;
        location / { try_files `$uri `$uri/ /index.php?`$query_string; }
        location ~ \.php$ {
            include "$fastcgi";
            fastcgi_param SCRIPT_FILENAME `$document_root`$fastcgi_script_name;
            fastcgi_pass php;
            fastcgi_read_timeout 60s;
        }
    }
}
"@ | Out-File -Encoding ascii (Join-Path $prefijo 'conf\nginx.conf')

Start-Process -FilePath $nginx -ArgumentList '-p', "$prefijo\" -WorkingDirectory $prefijo -WindowStyle Hidden
Start-Sleep -Seconds 2
Get-Process nginx -ErrorAction SilentlyContinue | ForEach-Object { try { $_.PriorityClass = 'AboveNormal' } catch {} }
try {
    $r = Invoke-WebRequest -UseBasicParsing "http://127.0.0.1:$Puerto/api/v1/salud" -TimeoutSec 15
    Write-Host "Servidor de carga listo en http://127.0.0.1:$Puerto con $Trabajadores procesos PHP (salud: $($r.StatusCode))."
} catch {
    Write-Error "El servidor no respondió: $($_.Exception.Message). Revise $prefijo\logs\error.log"
}
