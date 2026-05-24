<?php

namespace App\Console\Commands;

use App\Models\TrackClick;
use Illuminate\Console\Command;

class ArchiveOldClicks extends Command
{
    protected $signature = 'ei:archive-clicks {--days=90 : Delete click rows older than this many days}';

    protected $description = 'Delete track_clicks rows older than --days (default 90) to keep the table bounded.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = TrackClick::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} click rows older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
