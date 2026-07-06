<?php

namespace SparrowhawkLabs\Jess\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SparrowhawkLabs\Jess\Exceptions\FragmentExecutionException;
use SparrowhawkLabs\Jess\FragmentRegistry;
use SparrowhawkLabs\Jess\Support\DemoSession;
use SparrowhawkLabs\Jess\Support\StateRecipe;

/**
 * Provisions per-session demo SQLite copies and switches the runtime default
 * DB connection to point at them. The switch mechanism (purge + config swap,
 * connection name held fixed) was verified in spike/ — see PLAN.md リスク§1.
 */
class DemoSessionManager
{
    public function __construct(
        private readonly FragmentRegistry $fragments,
    ) {
    }

    public function provision(StateRecipe $recipe): DemoSession
    {
        $templatePath = (string) config('jess.template_db_path');

        if ($templatePath === '' || ! is_file($templatePath)) {
            throw new RuntimeException(
                "jess.template_db_path is not configured or the file is missing: [{$templatePath}]"
            );
        }

        $storageDir = $this->storageDir();

        if (! is_dir($storageDir) && ! mkdir($storageDir, 0755, true) && ! is_dir($storageDir)) {
            throw new RuntimeException("Could not create jess demo DB storage directory: [{$storageDir}]");
        }

        $uuid = (string) Str::uuid();
        $targetPath = $storageDir . '/' . $uuid . '.sqlite';

        if (! copy($templatePath, $targetPath)) {
            throw new RuntimeException("Could not copy jess template DB to [{$targetPath}]");
        }

        $connection = (string) config('jess.connection', 'jess_demo');
        $originalDefault = (string) config('database.default');

        // Apply fragments against the new file while it's the default
        // connection (host Seeders reference no connection name), then
        // restore whatever was default before — provisioning itself must
        // not leave global state switched.
        $this->withDemoConnection($connection, $targetPath, function () use ($recipe) {
            foreach ($recipe->fragments as $name) {
                // Let an unregistered-name lookup fail as itself (a config/
                // typo error, not a runtime failure) — only the closure's
                // own execution gets wrapped with fragment-attributed context.
                $callback = $this->fragments->get($name);

                try {
                    $callback();
                } catch (\Throwable $e) {
                    throw FragmentExecutionException::forFragment($name, $e);
                }
            }
        });

        $recipeLabel = implode(',', $recipe->fragments);
        $expiresAt = now()->addHours((int) config('jess.cleanup_after_hours', 24));

        DB::connection($originalDefault)->table('jess_demo_sessions')->insert([
            'uuid' => $uuid,
            'recipe' => $recipeLabel,
            'demo_db_path' => $targetPath,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new DemoSession($uuid, $recipeLabel, $targetPath, $expiresAt);
    }

    /**
     * Look up a previously provisioned, still-unexpired session by uuid —
     * used by the per-request middleware to resolve the Cookie it receives
     * back into a DemoSession worth activate()-ing.
     */
    public function find(string $uuid): ?DemoSession
    {
        $row = DB::table('jess_demo_sessions')
            ->where('uuid', $uuid)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        return new DemoSession($row->uuid, $row->recipe, $row->demo_db_path, new \DateTimeImmutable($row->expires_at));
    }

    /**
     * Point the runtime default connection at this session's SQLite file for
     * the remainder of the request. Intended for a per-request middleware
     * (Phase 3) that resolves a session identifier (e.g. from a Cookie) on
     * every request — unlike provision(), this does not restore the prior
     * default; the switch is meant to last for the rest of the request.
     */
    public function activate(DemoSession $session): void
    {
        $connection = (string) config('jess.connection', 'jess_demo');
        $this->configureConnection($connection, $session->sqlitePath);
        config(['database.default' => $connection]);
    }

    private function withDemoConnection(string $connection, string $path, Closure $callback): void
    {
        $originalDefault = (string) config('database.default');

        $this->configureConnection($connection, $path);
        config(['database.default' => $connection]);

        try {
            $callback();
        } finally {
            DB::purge($connection);
            config(['database.default' => $originalDefault]);
        }
    }

    private function configureConnection(string $connection, string $path): void
    {
        DB::purge($connection);
        config([
            "database.connections.{$connection}" => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
            ],
        ]);
    }

    private function storageDir(): string
    {
        $configured = (string) config('jess.demo_db_storage_path');

        return str_starts_with($configured, '/')
            ? $configured
            : storage_path($configured);
    }
}
