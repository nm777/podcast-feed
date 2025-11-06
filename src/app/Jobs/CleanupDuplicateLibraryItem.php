<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupDuplicateLibraryItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $libraryItem;

    /**
     * Create a new job instance.
     */
    public function __construct(LibraryItem $libraryItem)
    {
        $this->libraryItem = $libraryItem;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh the library item to get current state
        $libraryItem = $this->libraryItem->fresh();

        if (! $libraryItem || ! $libraryItem->is_duplicate) {
            return;
        }

        // Delete the duplicate library item
        $libraryItem->delete();
    }
}
