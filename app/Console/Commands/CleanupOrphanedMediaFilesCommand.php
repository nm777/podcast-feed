<?php

namespace App\Console\Commands;

use App\Jobs\CleanupOrphanedMediaFiles as CleanupOrphanedMediaFilesJob;
use Illuminate\Console\Command;

class CleanupOrphanedMediaFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:cleanup-orphaned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned media files that are no longer referenced by any library items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching cleanup job for orphaned media files...');

        CleanupOrphanedMediaFilesJob::dispatch();

        $this->info('Cleanup job dispatched successfully!');

        return self::SUCCESS;
    }
}
