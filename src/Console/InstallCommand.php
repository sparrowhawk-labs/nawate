<?php

namespace Tatun55\Nawate\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'nawate:install
                            {--force : Overwrite existing config file}
                            {--no-migrate : Skip running migrations}
                            {--no-docs : Skip publishing docs and the CLAUDE.md pointer}';

    protected $description = 'Install nawate: publish config, run migrations, publish docs + CLAUDE.md pointer.';

    public function handle(): int
    {
        $this->info('Publishing config...');
        $this->call('vendor:publish', [
            '--tag' => 'nawate-config',
            '--force' => (bool) $this->option('force'),
        ]);

        if (! $this->option('no-migrate')) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        if (! $this->option('no-docs')) {
            $this->info('Publishing docs...');
            $this->call('vendor:publish', [
                '--tag' => 'nawate-docs',
                '--force' => true,
            ]);
            $this->ensureClaudeMdPointer();
        }

        $this->newLine();
        $this->info('nawate installed.');
        $this->line('  1. Set NAWATE_ENABLED=true in .env for local/staging/demo environments only.');
        $this->line('  2. Register state recipes from your AppServiceProvider (Phase 2).');
        $this->line('  - Override config: php artisan vendor:publish --tag=nawate-config --force');

        return self::SUCCESS;
    }

    /**
     * Add a one-line pointer to the published docs in the host app's CLAUDE.md
     * (created if missing), so Claude Code sessions in the host can find the
     * nawate docs. Idempotent: skipped when the pointer path already appears.
     */
    private function ensureClaudeMdPointer(): void
    {
        $pointer = '- [nawate](docs/nawate/README.md) — signed-URL state switching for manual demo/verification（状態レシピ・DB分離の使い方はここ）';
        $path = base_path('CLAUDE.md');

        $existing = is_file($path) ? file_get_contents($path) : '';

        if (str_contains($existing, 'docs/nawate/README.md')) {
            $this->line('CLAUDE.md pointer already present.');

            return;
        }

        $content = $existing === ''
            ? $pointer . "\n"
            : rtrim($existing) . "\n" . $pointer . "\n";

        file_put_contents($path, $content);
        $this->info('Added nawate docs pointer to CLAUDE.md.');
    }
}
