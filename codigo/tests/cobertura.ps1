# Genera el reporte de cobertura en local con el Xdebug que trae Laravel Herd (sin modificar php.ini).
# Uso, desde la carpeta codigo:   powershell -ExecutionPolicy Bypass -File tests\cobertura.ps1 [-Minimo 80] [-Abrir]
param([int]$Minimo = 80, [switch]$Abrir)

$php = Join-Path $env:USERPROFILE '.config\herd\bin\php84\php.exe'
$xdebug = 'C:\Program Files\Herd\resources\app.asar.unpacked\resources\bin\xdebug\xdebug-8.4.dll'
foreach ($ruta in @($php, $xdebug)) {
    if (-not (Test-Path $ruta)) { Write-Error "No se encontró $ruta. Instale Laravel Herd con PHP 8.4."; exit 2 }
}

& $php -d "zend_extension=$xdebug" -d xdebug.mode=coverage -d memory_limit=2G vendor/bin/phpunit `
    --coverage-html cobertura/html --coverage-clover cobertura/clover.xml --coverage-text --only-summary-for-coverage-text
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& $php tests/cobertura-minima.php cobertura/clover.xml $Minimo
$resultado = $LASTEXITCODE
Write-Host "Reporte HTML: $(Resolve-Path cobertura\html\index.html)"
if ($Abrir) { Start-Process (Resolve-Path cobertura\html\index.html) }
exit $resultado
