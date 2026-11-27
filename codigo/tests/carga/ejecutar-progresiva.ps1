# Runs the progressive k6 test and samples CPU and RAM every 2 s, split by process
# (PHP, MySQL, nginx and k6 itself), to locate the bottleneck at each level.
# Uso: powershell -File tests\carga\ejecutar-progresiva.ps1 -Token <PRUEBAS_CARGA_TOKEN> [-Base http://127.0.0.1:8020]
#      [-Niveles 50,100,250,500,1000,1500,2000] [-Etiqueta eq-progresiva] [-Salida ..\sprint-2\evidencias]
param(
    [Parameter(Mandatory)][string]$Token,
    [string]$Base = 'http://127.0.0.1:8020',
    [string]$Niveles = '50,100,250,500,1000,1500,2000',
    [int]$Subida = 30,
    [int]$Mantener = 90,
    [string]$Etiqueta = 'eq-progresiva',
    [string]$Salida = ''
)
$ErrorActionPreference = 'Stop'
# $PSScriptRoot is not available in parameter defaults when run with "powershell -File" on 5.1.
if (-not $Salida) { $Salida = Join-Path $PSScriptRoot '..\..\..\sprint-2\evidencias' }
New-Item -ItemType Directory -Force $Salida | Out-Null
$Salida = (Resolve-Path $Salida).Path
$k6 = (Get-Command k6 -ErrorAction SilentlyContinue).Source
if (-not $k6) { $k6 = 'C:\Program Files\k6\k6.exe' }
$inicio = Get-Date

$muestreo = Start-Job -ArgumentList $inicio -ScriptBlock {
    param($inicio)
    $nucleos = (Get-CimInstance Win32_ComputerSystem).NumberOfLogicalProcessors
    while ($true) {
        $total = (Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'").PercentProcessorTime
        $procesos = Get-CimInstance Win32_PerfFormattedData_PerfProc_Process |
            Where-Object { $_.Name -match '^(php-cgi|mysqld|nginx|k6)(#\d+)?$' }
        $por = @{}
        foreach ($p in $procesos) {
            $n = ($p.Name -replace '#\d+$', '')
            $por[$n] = ($por[$n] + $p.PercentProcessorTime)
        }
        $so = Get-CimInstance Win32_OperatingSystem
        [pscustomobject]@{
            s = [int]((Get-Date) - $inicio).TotalSeconds
            cpu = [int]$total
            php = [math]::Round(($por['php-cgi'] / $nucleos), 1)
            mysql = [math]::Round(($por['mysqld'] / $nucleos), 1)
            nginx = [math]::Round(($por['nginx'] / $nucleos), 1)
            k6 = [math]::Round(($por['k6'] / $nucleos), 1)
            ram_gb = [math]::Round(($so.TotalVisibleMemorySize - $so.FreePhysicalMemory) / 1MB, 2)
        }
        Start-Sleep -Seconds 2
    }
}
try {
    $ErrorActionPreference = 'Continue'   # k6 writes warnings to stderr; they must not stop the run
    # Quoted: PowerShell would otherwise split "50,100,..." into separate arguments.
    $resumen = Join-Path $Salida "$Etiqueta-resumen.json"
    & $k6 run --quiet -e "BASE=$Base" -e "TOKEN=$Token" -e "NIVELES=$Niveles" -e "SUBIDA=$Subida" -e "MANTENER=$Mantener" `
        -e "SALIDA=$resumen" (Join-Path $PSScriptRoot 'eq-progresiva.js') 2>&1 |
        Where-Object { "$_" -notmatch 'SSLKEYLOGFILE' } | Tee-Object -FilePath (Join-Path $Salida "$Etiqueta.txt")
} finally {
    Stop-Job $muestreo
    $muestras = Receive-Job $muestreo
    Remove-Job $muestreo
}

# Resources per level, using the same plateau windows as the k6 script.
$lista = $Niveles.Split(',') | ForEach-Object { [int]$_ }
$t = 0
$porNivel = foreach ($n in $lista) {
    $a = $t + $Subida; $b = $a + $Mantener; $t = $b
    $m = $muestras | Where-Object { $_.s -ge $a -and $_.s -lt $b }
    [pscustomobject]@{
        nivel = $n
        cpu_promedio = [math]::Round(($m.cpu | Measure-Object -Average).Average, 1)
        cpu_maximo = ($m.cpu | Measure-Object -Maximum).Maximum
        php = [math]::Round(($m.php | Measure-Object -Average).Average, 1)
        mysql = [math]::Round(($m.mysql | Measure-Object -Average).Average, 1)
        nginx = [math]::Round(($m.nginx | Measure-Object -Average).Average, 1)
        k6 = [math]::Round(($m.k6 | Measure-Object -Average).Average, 1)
        ram_max_gb = ($m.ram_gb | Measure-Object -Maximum).Maximum
    }
}
[pscustomobject]@{ muestras = $muestras; por_nivel = $porNivel } | ConvertTo-Json -Depth 4 |
    Out-File -Encoding utf8 (Join-Path $Salida "$Etiqueta-recursos.json")
''
'Recursos por nivel (CPU % del equipo; columnas por proceso como % del total de núcleos):'
$porNivel | Format-Table -AutoSize | Out-String | Write-Host
