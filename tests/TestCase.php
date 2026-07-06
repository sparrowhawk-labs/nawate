<?php

namespace SparrowhawkLabs\Jess\Tests;

use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use SparrowhawkLabs\Jess\JessServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            JessServiceProvider::class,
        ];
    }

    /**
     * A real request only ever lives for one process, so a connection
     * switch never outlives it — but Pest reuses this same process across
     * every test function, so undo the switch here or it bleeds into
     * whichever test runs next.
     */
    protected function tearDown(): void
    {
        $connection = (string) config('jess.connection', 'jess_demo');
        DB::purge($connection);
        config(['database.default' => 'testing']);

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        $app['config']->set('jess.enabled', true);
        $app['config']->set('jess.template_db_path', $this->makeTemplateDatabase());
        $app['config']->set('jess.demo_db_storage_path', sys_get_temp_dir() . '/jess-tests-' . uniqid());

        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \Illuminate\Foundation\Auth\User::class,
        ]);
    }

    /**
     * Stand-in for "the host app's migrated, empty-data template SQLite".
     * Regenerated fresh per test run so it can never drift from what the
     * tests actually assert against.
     */
    private function makeTemplateDatabase(): string
    {
        $path = sys_get_temp_dir() . '/jess-template-' . uniqid() . '.sqlite';
        touch($path);

        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, is_repeat_customer INTEGER DEFAULT 0)');
        $pdo->exec("INSERT INTO users (id, name, is_repeat_customer) VALUES (1, 'Guest', 0)");

        return $path;
    }
}
