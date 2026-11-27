<?php

// Dedicated loopback origin for the public payment test tunnel. Never point the
// tunnel at the unrestricted development server on port 8001.
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
    http_response_code(404); exit('No encontrado.');
}
$public = realpath(__DIR__.'/../../public');
$asset = preg_match('#^/(?:css/[a-zA-Z0-9_/-]+\.css|js/[a-zA-Z0-9_/-]+\.js|img/municipalidad/[a-zA-Z0-9_/-]+\.(?:png|jpg|jpeg|webp)|img/logo\.jpg)$#D', $path);
if ($asset && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    $file = realpath($public.$path);
    if (!$file || !str_starts_with(strtolower(str_replace('\\', '/', $file)), strtolower(str_replace('\\', '/', $public)).'/') || !is_file($file)) {
        http_response_code(404); exit('No encontrado.');
    }
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    header('Content-Type: '.$types[pathinfo($file, PATHINFO_EXTENSION)]);
    header('Cache-Control: public, max-age=3600');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($file);
    return;
}
if ($path === '/') { header('Location: /tramite', true, 302); return; }
if (!preg_match('#^/tramite(?:/|$)#D', $path) && !in_array($path, ['/pagos/mercadopago/notificacion', '/pagos/mercadopago/retorno'], true)) {
    http_response_code(404); exit('No encontrado.');
}
putenv('APP_DEBUG=false'); $_ENV['APP_DEBUG'] = 'false'; $_SERVER['APP_DEBUG'] = 'false';
putenv('SESSION_SECURE_COOKIE=true'); $_ENV['SESSION_SECURE_COOKIE'] = 'true'; $_SERVER['SESSION_SECURE_COOKIE'] = 'true';
// This listener is only reachable on 127.0.0.1 and is served externally by HTTPS.
$_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = 443;
define('LARAVEL_START', microtime(true));
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$app->make('config')->set('app.debug', false);
$app->handleRequest(Illuminate\Http\Request::capture());
