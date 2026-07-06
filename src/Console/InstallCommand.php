<?php

namespace SparrowhawkLabs\Jess\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'jess:install
                            {--force : Overwrite existing config file}
                            {--no-migrate : Skip running migrations}
                            {--no-docs : Skip publishing docs and the AGENTS.md/CLAUDE.md wiring}';

    protected $description = 'Install jess: publish config, run migrations, publish two-layer agent docs.';

    public function handle(): int
    {
        $this->info('Publishing config...');
        $this->call('vendor:publish', [
            '--tag' => 'jess-config',
            '--force' => (bool) $this->option('force'),
        ]);

        if (! $this->option('no-migrate')) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        if (! $this->option('no-docs')) {
            $this->info('Publishing docs...');
            $this->call('vendor:publish', [
                '--tag' => 'jess-docs',
                '--force' => true,
            ]);
            $this->ensureAgentsMdCore();
            $this->ensureClaudeMdImportsAgents();
        }

        $this->newLine();
        $this->info('jess installed.');
        $this->line('  1. Set JESS_ENABLED=true in .env for local/staging/demo environments only.');
        $this->line('  2. Register state recipes from your AppServiceProvider — see AGENTS.md / docs/jess/README.md.');
        $this->line('  - Override config: php artisan vendor:publish --tag=jess-config --force');

        return self::SUCCESS;
    }

    /**
     * Append jess's core-info block to the host app's AGENTS.md (created if
     * missing) — the cross-tool convention file most coding agents other than
     * Claude Code read natively. Idempotent via the `jess:core:start` marker.
     */
    private function ensureAgentsMdCore(): void
    {
        $path = base_path('AGENTS.md');
        $existing = is_file($path) ? file_get_contents($path) : '';

        if (str_contains($existing, '<!-- jess:core:start -->')) {
            $this->line('AGENTS.md core section already present.');

            return;
        }

        $block = file_get_contents(__DIR__ . '/../../resources/docs/agents-core.md');

        $content = $existing === ''
            ? $block
            : rtrim($existing) . "\n\n" . $block;

        file_put_contents($path, $content);
        $this->info('Added jess core section to AGENTS.md.');
    }

    /**
     * Claude Code reads CLAUDE.md, not AGENTS.md, unless CLAUDE.md imports it
     * (`@AGENTS.md`) — see https://code.claude.com/docs/en/memory (AGENTS.md
     * section). Ensure that import so Claude Code sessions pick up the same
     * core section without duplicating its content into CLAUDE.md. Creates a
     * minimal CLAUDE.md if the host app has none. Idempotent.
     */
    private function ensureClaudeMdImportsAgents(): void
    {
        $path = base_path('CLAUDE.md');
        $existing = is_file($path) ? file_get_contents($path) : '';

        if (str_contains($existing, '@AGENTS.md')) {
            $this->line('CLAUDE.md already imports AGENTS.md.');

            return;
        }

        $content = $existing === ''
            ? "@AGENTS.md\n"
            : "@AGENTS.md\n\n" . ltrim($existing);

        file_put_contents($path, $content);
        $this->info('Added @AGENTS.md import to CLAUDE.md.');
    }
}
