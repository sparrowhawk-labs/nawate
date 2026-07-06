<?php

namespace Tatun55\Nawate;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\ServiceProvider;
use Tatun55\Nawate\Console\CleanupCommand;
use Tatun55\Nawate\Console\InstallCommand;
use Tatun55\Nawate\FragmentRegistry;
use Tatun55\Nawate\Http\Middleware\SwitchDemoConnection;
use Tatun55\Nawate\Services\DemoSessionManager;

class NawateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nawate.php', 'nawate');

        $this->app->singleton(FragmentRegistry::class);
        $this->app->singleton(DemoSessionManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Inert unless explicitly enabled — no route, no middleware, no
        // exception handling registered at all in the disabled state.
        if (config('nawate.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/nawate.php');
            $this->app['router']->pushMiddlewareToGroup('web', SwitchDemoConnection::class);
            $this->registerExpiredLinkPage();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CleanupCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/nawate.php' => config_path('nawate.php'),
            ], 'nawate-config');

            $this->publishes([
                __DIR__ . '/../README.md' => base_path('docs/nawate/README.md'),
            ], 'nawate-docs');
        }
    }

    /**
     * A signed nawate link that's expired or been tampered with throws
     * Laravel's built-in InvalidSignatureException (plain 403 by default).
     * Replace that with a plain-language Japanese explanation, scoped to
     * the nawate route only — everything else keeps default handling.
     */
    private function registerExpiredLinkPage(): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(
            function (InvalidSignatureException $e, $request) {
                if (! $request->routeIs('nawate.state')) {
                    return null;
                }

                $ttl = (int) config('nawate.signed_url_ttl', 60);

                return response(
                    '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
                    . '<title>nawate: リンクが無効です</title></head>'
                    . '<body style="font-family: sans-serif; padding: 2rem; max-width: 40rem;">'
                    . '<h1>このデモ状態切替リンクは無効です</h1>'
                    . "<p>署名が不正か、有効期限（{$ttl}分）が切れています。リンクを発行し直してください。</p>"
                    . '</body></html>',
                    403,
                );
            }
        );
    }
}
