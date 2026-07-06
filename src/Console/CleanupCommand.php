<?php

namespace Tatun55\Nawate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupCommand extends Command
{
    protected $signature = 'nawate:cleanup
                            {--dry-run : List what would be removed without deleting anything}';

    protected $description = 'Delete expired nawate demo sessions (SQLite copy + bookkeeping row).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $expired = DB::table('nawate_demo_sessions')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired nawate demo sessions.');

            return self::SUCCESS;
        }

        foreach ($expired as $row) {
            if ($dryRun) {
                $this->line("[dry-run] would remove {$row->uuid} ({$row->demo_db_path})");

                continue;
            }

            if (is_file($row->demo_db_path)) {
                unlink($row->demo_db_path);
            }

            DB::table('nawate_demo_sessions')->where('id', $row->id)->delete();
            $this->line("removed {$row->uuid}");
        }

        $this->info($dryRun
            ? sprintf('%d expired session(s) would be removed (dry-run).', $expired->count())
            : sprintf('%d expired session(s) removed.', $expired->count()));

        return self::SUCCESS;
    }
}
