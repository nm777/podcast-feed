<?php

namespace App\Jobs;

use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedMediaFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orphanedFiles = MediaFile::whereDoesntHave('libraryItems')->get();

        foreach ($orphanedFiles as $mediaFile) {
            // Delete the actual file from storage
            if ($mediaFile->file_path) {
                Storage::disk('local')->delete($mediaFile->file_path);
            }

            // Delete the database record
            $mediaFile->delete();
        }

        if ($orphanedFiles->isNotEmpty()) {
            \Log::info("Cleaned up {$orphanedFiles->count()} orphaned media files");
        }
    }
}
