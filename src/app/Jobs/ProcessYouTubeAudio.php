<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\YouTube\YouTubeProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessYouTubeAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected LibraryItem $libraryItem,
        protected string $youtubeUrl,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(YouTubeProcessingService $processingService): void
    {
        Log::info('ProcessYouTubeAudio job started', [
            'library_item_id' => $this->libraryItem->id,
            'youtube_url' => $this->youtubeUrl,
        ]);

        try {
            $processingService->processYouTubeUrl($this->libraryItem, $this->youtubeUrl);
        } catch (\Exception $e) {
            Log::error('ProcessYouTubeAudio job failed', [
                'library_item_id' => $this->libraryItem->id,
                'youtube_url' => $this->youtubeUrl,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            $this->libraryItem->update([
                'processing_status' => ProcessingStatusType::FAILED,
                'processing_completed_at' => now(),
                'processing_error' => 'YouTube processing failed: '.$e->getMessage(),
            ]);
        }
    }
}
