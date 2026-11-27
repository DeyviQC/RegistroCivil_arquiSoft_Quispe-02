# Ejecuta una corrida de k6 contra la base de carga y mide CPU y RAM del equipo mientras dura.
# Uso: powershell -File tests\carga\ejecutar-carga.ps1 -Usuarios 50 -Duracion 2m -Etiqueta ac03-10k-50vus -Token <PRUEBAS_CARGA_TOKEN>
# Requisitos: .env apuntando a registro_civil_carga con MAIL_MAILER=log y el mismo PRUEBAS_CARGA_TOKEN (ver AC01).
param(
    [int]$Usuarios = 20,
    [string]$Duracion = '2m',
    [Parameter(Mandatory)][string]$Etiqueta,
    [Parameter(Mandatory)][string]$Token,
    [string]$Base = 'http://registro-civil.test',
    [string]$Datos = 'datos.json'
)
$ErrorActionPreference = 'Stop'
$raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$salida = Join-Path $raiz 'docs\calidad\evidencias'
New-Item -ItemType Directory -Force $salida | Out-Null
$k6 = (Get-Command k6 -ErrorAction SilentlyContinue).Source
if (-not $k6) { $k6 = 'C:\Program Files\k6\k6.exe' }

# Sample CPU and free memory every 2 s while k6 runs (CIM classes are not localized, unlike Get-Counter).
$muestreo = Start-Job -ScriptBlock {
    while ($true) {
        $cpu = (Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'").PercentProcessorTime
        $so = Get-CimInstance Win32_OperatingSystem
        [pscustomobject]@{ cpu = [int]$cpu; ram_usada_gb = [math]::Round(($so.TotalVisibleMemorySize - $so.FreePhysicalMemory) / 1MB, 2) }
        Start-Sleep -Seconds 2
    }
}
try {
    # k6 writes warnings to stderr (e.g. the antivirus SSLKEYLOGFILE notice): they must not stop the run.
    $ErrorActionPreference = 'Continue'
    & $k6 run -e BASE=$Base -e TOKEN=$Token -e VUS=$Usuarios -e DURACION=$Duracion -e DATOS=$Datos `
        --summary-export (Join-Path $salida "$Etiqueta.json") (Join-Path $PSScriptRoot 'ac01-rendimiento.js') 2>&1 |
        Out-File -Encoding utf8 (Join-Path $salida "$Etiqueta.txt")
} finally {
    Stop-Job $muestreo
    $m = Receive-Job $muestreo; Remove-Job $muestreo
}
$recursos = [pscustomobject]@{
    muestras = $m.Count
    cpu_promedio = [math]::Round(($m.cpu | Measure-Object -Average).Average, 1)
    cpu_maximo = ($m.cpu | Measure-Object -Maximum).Maximum
    ram_usada_max_gb = ($m.ram_usada_gb | Measure-Object -Maximum).Maximum
}
$recursos | ConvertTo-Json | Out-File -Encoding utf8 (Join-Path $salida "$Etiqueta-recursos.json")

# Short summary on screen: p95 per operation, failures and resources.
$r = Get-Content (Join-Path $salida "$Etiqueta.json") -Raw | ConvertFrom-Json
foreach ($op in 'busqueda_publica', 'seguimiento', 'busqueda_archivo', 'bandeja') {
    $met = $r.metrics."http_req_duration{operacion:$op}"
    '{0,-17} p95={1,8:N1} ms  med={2,8:N1} ms' -f $op, $met.'p(95)', $met.med
}
'fallidas={0:P2}  peticiones={1}  verificaciones OK={2:P1}' -f $r.metrics.http_req_failed.value, $r.metrics.http_reqs.count, $r.metrics.checks.value
'CPU promedio={0}%  CPU max={1}%  RAM usada max={2} GB' -f $recursos.cpu_promedio, $recursos.cpu_maximo, $recursos.ram_usada_max_gb
