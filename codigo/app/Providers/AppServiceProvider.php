<?php

namespace App\Providers;

use App\Models\Acta;
use App\Models\Auditoria;
use App\Models\User;
use App\Policies\ActaPolicy;
use App\Policies\AuditoriaPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('portal-pago', function (\Illuminate\Http\Request $request) {
            $respuesta = function ($request, array $headers) {
                return response()->view('portal.pago-espera', ['segundos' => max(1, (int) ($headers['Retry-After'] ?? 60))], 429, $headers)
                    ->header('Cache-Control', 'private, no-store');
            };
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by('sesion:'.hash('sha256', $request->session()->getId()))->response($respuesta),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('red:'.$request->ip())->response($respuesta),
            ];
        });
        \Illuminate\Support\Facades\RateLimiter::for('portal-datos', function (\Illuminate\Http\Request $request) {
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('sesion:'.hash('sha256', $request->session()->getId())),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by('red:'.$request->ip()),
            ];
        });
        if (config('mail.mailers.smtp.cafile')) {
            $this->app->make('mail.manager')->extend('smtp', function (array $config) {
                if (!is_readable($config['cafile'])) {
                    throw new \RuntimeException('No se encuentra el archivo de certificados de correo.');
                }
                $config['require_tls'] = true;
                $factory = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory();
                $transport = $factory->create(new \Symfony\Component\Mailer\Transport\Dsn(
                    $config['scheme'] ?? 'smtp', $config['host'], $config['username'] ?? null,
                    $config['password'] ?? null, $config['port'] ?? 587, $config
                ));
                $stream = $transport instanceof \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport ? $transport->getStream() : null;
                if (! $stream instanceof \Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream) {
                    throw new \RuntimeException('El transporte SMTP no admite la configuración TLS requerida.');
                }
                $options = $stream->getStreamOptions();
                $options['ssl'] = array_merge($options['ssl'] ?? [], [
                    'cafile' => $config['cafile'], 'verify_peer' => true, 'verify_peer_name' => true,
                ]);
                $stream->setStreamOptions($options);
                $stream->setTimeout($config['timeout'] ?? 15);
                return $transport;
            });
        }
        Paginator::useBootstrapFive();
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $pendientes = auth()->check()
                ? Acta::where('estado', Acta::ESTADO_BORRADOR)->whereNull('pdf_ruta')->count() : 0;
            $view->with('pendientesPdfMenu', $pendientes);
        });
        Gate::policy(Acta::class, ActaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Auditoria::class, AuditoriaPolicy::class);
    }
}
