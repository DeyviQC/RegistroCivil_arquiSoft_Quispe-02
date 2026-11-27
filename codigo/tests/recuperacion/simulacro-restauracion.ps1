# EQ-08 Recuperabilidad — simulacro de restauración completo y medido.
#   1. Crea un respaldo real con "php artisan respaldo:crear" (base de datos, documentos y APP_KEY cifrada).
#   2. Lo restaura en OTRA base de datos y OTRA carpeta, como en un equipo nuevo.
#   3. Verifica tabla por tabla, archivo por archivo y la lectura de los datos cifrados (verificar-restauracion.php).
# Solo trabaja sobre bases cuyo nombre contenga "prueba" o "carga"; nunca muestra claves ni frases.
#
# Uso, desde la carpeta codigo:
#   powershell -ExecutionPolicy Bypass -File tests\recuperacion\simulacro-restauracion.ps1 -Base registro_civil_sprint2_prueba
param(
    [Parameter(Mandatory)][string]$Base,
    [string]$Trabajo = (Join-Path $env:TEMP 'registro-civil-simulacro'),
    [string]$MysqlBin = (Join-Path $env:LOCALAPPDATA 'com.tinyapp.DBngin\Binaries\mysql\9.7.1\bin'),
    [string]$Evidencia = '',
    [switch]$Conservar
)
$ErrorActionPreference = 'Stop'
if ($Base -notmatch 'prueba|carga') { Write-Error 'Solo se permite sobre bases de prueba (el nombre debe contener "prueba" o "carga").'; exit 2 }
$codigo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if (-not $Evidencia) { $Evidencia = Join-Path $codigo '..\sprint-2\evidencias\eq08-restauracion.json' }
$php = Join-Path $env:USERPROFILE '.config\herd\bin\php84\php.exe'
$mysql = Join-Path $MysqlBin 'mysql.exe'
$restaurada = "${Base}_restaurada"

# Connection data and APP_KEY come from the project's .env (values are never printed).
$env_ = @{}
Get-Content (Join-Path $codigo '.env') | Where-Object { $_ -match '^\s*([A-Z_]+)=(.*)$' } | ForEach-Object { $env_[$Matches[1]] = $Matches[2].Trim('"') }
$hostDb = if ($env_['DB_HOST']) { $env_['DB_HOST'] } else { '127.0.0.1' }
$puerto = if ($env_['DB_PORT']) { $env_['DB_PORT'] } else { '3306' }
$usuario = if ($env_['DB_USERNAME']) { $env_['DB_USERNAME'] } else { 'root' }
$claveDb = [string]$env_['DB_PASSWORD']
$env:MYSQL_PWD = $claveDb
$frase = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object { [char]$_ })

if (Test-Path $Trabajo) { Remove-Item -Recurse -Force $Trabajo }
New-Item -ItemType Directory -Force $Trabajo | Out-Null
$respaldo = Join-Path $Trabajo 'respaldo'
$storageNuevo = Join-Path $Trabajo 'equipo-nuevo\storage\app\private'
$tiempos = [ordered]@{}

# 1. Backup, with the project's own command.
$env:DB_DATABASE = $Base
$env:RESPALDO_RUTA = $respaldo
$env:RESPALDO_MYSQLDUMP = Join-Path $MysqlBin 'mysqldump.exe'
$env:RESPALDO_FRASE = $frase
$reloj = [Diagnostics.Stopwatch]::StartNew()
Push-Location $codigo
& $php artisan respaldo:crear
$codigoSalida = $LASTEXITCODE
Pop-Location
$tiempos['respaldo_s'] = [math]::Round($reloj.Elapsed.TotalSeconds, 1)
if ($codigoSalida -ne 0) { Write-Error 'El respaldo falló.'; exit 1 }
$volcado = Get-ChildItem (Join-Path $respaldo 'base-datos') -Filter '*.sql.gz' | Sort-Object LastWriteTime | Select-Object -Last 1

# 2. Restore on a "new machine": another database and another folder. This is the recovery time (RTO).
$reloj = [Diagnostics.Stopwatch]::StartNew()
& $mysql "--host=$hostDb" "--port=$puerto" "--user=$usuario" -e "DROP DATABASE IF EXISTS ``$restaurada``; CREATE DATABASE ``$restaurada`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$sql = Join-Path $Trabajo 'volcado.sql'
& $php -r "`$i = gzopen(`$argv[1], 'rb'); `$o = fopen(`$argv[2], 'wb'); while (!gzeof(`$i)) fwrite(`$o, gzread(`$i, 1048576)); gzclose(`$i); fclose(`$o);" $volcado.FullName $sql
cmd /c "`"$mysql`" --host=$hostDb --port=$puerto --user=$usuario $restaurada < `"$sql`""
if ($LASTEXITCODE -ne 0) { Write-Error 'La importación del volcado falló.'; exit 1 }
$tiempos['base_de_datos_s'] = [math]::Round($reloj.Elapsed.TotalSeconds, 1)
$parcial = $reloj.Elapsed.TotalSeconds
New-Item -ItemType Directory -Force $storageNuevo | Out-Null
Copy-Item -Recurse -Force (Join-Path $respaldo 'archivos\*') $storageNuevo
$tiempos['documentos_s'] = [math]::Round($reloj.Elapsed.TotalSeconds - $parcial, 1)
$tiempos['rto_s'] = [math]::Round($reloj.Elapsed.TotalSeconds, 1)

# 3. Verification (the config file holds secrets only while it runs).
$config = Join-Path $Trabajo 'verificacion.json'
[ordered]@{
    mysql = @{ host = $hostDb; port = $puerto; usuario = $usuario; clave = $claveDb }
    base_original = $Base; base_restaurada = $restaurada
    archivos_original = (Join-Path $codigo 'storage\app\private'); archivos_restaurados = $storageNuevo
    app_key_original = $env_['APP_KEY']; clave_cifrada = (Join-Path $respaldo 'app-key.enc'); frase = $frase
} | ConvertTo-Json | Out-File -Encoding utf8 $config
$reloj = [Diagnostics.Stopwatch]::StartNew()
try {
    $salida = & $php (Join-Path $PSScriptRoot 'verificar-restauracion.php') $config
    $aprobado = $LASTEXITCODE -eq 0
} finally {
    Remove-Item -Force $config
    Remove-Item -Force $sql -ErrorAction SilentlyContinue
}
$tiempos['verificacion_s'] = [math]::Round($reloj.Elapsed.TotalSeconds, 1)
$verificacion = ($salida -join "`n") | ConvertFrom-Json

$resultado = [ordered]@{
    fecha = (Get-Date).ToString('s'); base_original = $Base; base_restaurada = $restaurada
    volcado = @{ archivo = $volcado.Name; tamano_kb = [math]::Round($volcado.Length / 1KB, 1) }
    documentos_respaldados = (Get-ChildItem -Recurse -File (Join-Path $respaldo 'archivos')).Count
    tiempos = $tiempos; verificacion = $verificacion; aprobado = $aprobado
}
$resultado | ConvertTo-Json -Depth 6 | Out-File -Encoding utf8 $Evidencia
if (-not $Conservar) {
    & $mysql "--host=$hostDb" "--port=$puerto" "--user=$usuario" -e "DROP DATABASE IF EXISTS ``$restaurada``;"
    Remove-Item -Recurse -Force $Trabajo
}
Remove-Item Env:MYSQL_PWD, Env:RESPALDO_FRASE, Env:RESPALDO_RUTA, Env:RESPALDO_MYSQLDUMP, Env:DB_DATABASE -ErrorAction SilentlyContinue

''
"Respaldo: $($tiempos.respaldo_s) s · Restauración (RTO): $($tiempos.rto_s) s (base $($tiempos.base_de_datos_s) s + documentos $($tiempos.documentos_s) s)"
"Tablas de negocio idénticas: $($verificacion.resumen_tablas.de_negocio_identicas) de $($verificacion.resumen_tablas.de_negocio)"
"Documentos idénticos (SHA-256): $($verificacion.archivos.identicos) de $($verificacion.archivos.revisados)"
"APP_KEY recuperada y correcta: $($verificacion.clave.coincide_con_la_original) · frase incorrecta rechazada: $($verificacion.clave.frase_incorrecta_rechazada)"
"Solicitudes descifradas: $($verificacion.datos_cifrados.solicitudes_descifradas) (fallidas: $($verificacion.datos_cifrados.fallidas)) · índice DNI correcto: $($verificacion.datos_cifrados.indice_dni_correcto) de $($verificacion.datos_cifrados.con_dni)"
if ($aprobado) { 'Veredicto: APROBADO' } else { 'Veredicto: NO APROBADO'; exit 1 }
