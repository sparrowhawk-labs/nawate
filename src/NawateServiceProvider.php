<?php

namespace SparrowhawkLabs\Nawate;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\ServiceProvider;
use SparrowhawkLabs\Nawate\Console\CleanupCommand;
use SparrowhawkLabs\Nawate\Console\InstallCommand;
use SparrowhawkLabs\Nawate\FragmentRegistry;
use SparrowhawkLabs\Nawate\Http\Middleware\SwitchDemoConnection;
use SparrowhawkLabs\Nawate\Services\DemoSessionManager;

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
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'nawate');

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

            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/nawate'),
            ], 'nawate-lang');
        }
    }

    /**
     * A signed nawate link that's expired or been tampered with throws
     * Laravel's built-in InvalidSignatureException (plain 403 by default).
     * Replace that with a plain-language explanation, scoped to the nawate
     * route only — everything else keeps default handling. Text follows the
     * app's current locale (`app()->getLocale()`, driven by `APP_LOCALE` /
     * `config('app.locale')`) via nawate's own translation files
     * (`nawate::messages.*`, English + Japanese shipped; publish
     * `--tag=nawate-lang` to add more or override wording).
     */
    private function registerExpiredLinkPage(): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(
            function (InvalidSignatureException $e, $request) {
                if (! $request->routeIs('nawate.state')) {
                    return null;
                }

                $ttl = (int) config('nawate.signed_url_ttl', 60);
                $locale = e(app()->getLocale());

                return response(
                    "<!doctype html><html lang=\"{$locale}\"><head><meta charset=\"utf-8\">"
                    . '<title>' . e(__('nawate::messages.expired_title')) . '</title></head>'
                    . '<body style="font-family: sans-serif; padding: 2rem; max-width: 40rem;">'
                    . '<h1>' . e(__('nawate::messages.expired_heading')) . '</h1>'
                    . '<p>' . e(__('nawate::messages.expired_body', ['ttl' => $ttl])) . '</p>'
                    . '</body></html>',
                    403,
                );
            }
        );
    }
}
