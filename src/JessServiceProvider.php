<?php

namespace SparrowhawkLabs\Jess;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\ServiceProvider;
use SparrowhawkLabs\Jess\Console\CleanupCommand;
use SparrowhawkLabs\Jess\Console\InstallCommand;
use SparrowhawkLabs\Jess\FragmentRegistry;
use SparrowhawkLabs\Jess\Http\Middleware\SwitchDemoConnection;
use SparrowhawkLabs\Jess\Services\DemoSessionManager;

class JessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/jess.php', 'jess');

        $this->app->singleton(FragmentRegistry::class);
        $this->app->singleton(DemoSessionManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'jess');

        // Inert unless explicitly enabled — no route, no middleware, no
        // exception handling registered at all in the disabled state.
        if (config('jess.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/jess.php');
            $this->app['router']->pushMiddlewareToGroup('web', SwitchDemoConnection::class);
            $this->registerExpiredLinkPage();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CleanupCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/jess.php' => config_path('jess.php'),
            ], 'jess-config');

            $this->publishes([
                __DIR__ . '/../README.md' => base_path('docs/jess/README.md'),
            ], 'jess-docs');

            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/jess'),
            ], 'jess-lang');
        }
    }

    /**
     * A signed jess link that's expired or been tampered with throws
     * Laravel's built-in InvalidSignatureException (plain 403 by default).
     * Replace that with a plain-language explanation, scoped to the jess
     * route only — everything else keeps default handling. Text follows the
     * app's current locale (`app()->getLocale()`, driven by `APP_LOCALE` /
     * `config('app.locale')`) via jess's own translation files
     * (`jess::messages.*`, English + Japanese shipped; publish
     * `--tag=jess-lang` to add more or override wording).
     */
    private function registerExpiredLinkPage(): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(
            function (InvalidSignatureException $e, $request) {
                if (! $request->routeIs('jess.state')) {
                    return null;
                }

                $ttl = (int) config('jess.signed_url_ttl', 60);
                $locale = e(app()->getLocale());

                return response(
                    "<!doctype html><html lang=\"{$locale}\"><head><meta charset=\"utf-8\">"
                    . '<title>' . e(__('jess::messages.expired_title')) . '</title></head>'
                    . '<body style="font-family: sans-serif; padding: 2rem; max-width: 40rem;">'
                    . '<h1>' . e(__('jess::messages.expired_heading')) . '</h1>'
                    . '<p>' . e(__('jess::messages.expired_body', ['ttl' => $ttl])) . '</p>'
                    . '</body></html>',
                    403,
                );
            }
        );
    }
}
