$taskProjectRoot = $PSScriptRoot
$taskPhp = 'C:/Users/LOQ/.config/herd/bin/php84/php.exe'
Push-Location (Join-Path $taskProjectRoot 'public')
try {
    & $taskPhp -S 127.0.0.1:8001 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
} finally {
    Pop-Location
}