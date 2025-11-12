<?php

namespace App\Services\YouTube;

use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\YouTubeUrlValidator;
use Illuminate\Support\Facades\Log;

class YouTubeProcessingService
{
    public function __construct(
        private YouTubeDownloader $downloader,
        private YouTubeMetadataExtractor $metadataExtractor,
        private YouTubeFileProcessor $fileProcessor,
        private UnifiedDuplicateProcessor $duplicateProcessor,
    ) {}

    /**
     * Process YouTube URL and create/update library item.
     */
    public function processYouTubeUrl(LibraryItem $libraryItem, string $youtubeUrl): array
    {
        Log::info('ProcessYouTubeAudio job started', [
            'library_item_id' => $libraryItem->id,
            'youtube_url' => $youtubeUrl,
        ]);

        // Mark as processing
        $this->markAsProcessing($libraryItem);

        // Validate YouTube URL and extract video ID
        $videoId = YouTubeUrlValidator::extractVideoId($youtubeUrl);

        if (! $videoId) {
            Log::error('Failed to extract video ID from URL', [
                'library_item_id' => $libraryItem->id,
                'youtube_url' => $youtubeUrl,
            ]);
            $libraryItem->delete();

            return [
                'success' => false,
                'error' => 'Invalid YouTube URL',
            ];
        }

        Log::info('Extracted video ID', [
            'library_item_id' => $libraryItem->id,
            'video_id' => $videoId,
            'youtube_url' => $youtubeUrl,
        ]);

        // Check for duplicates first
        $duplicateResult = $this->duplicateProcessor->processUrlDuplicate($libraryItem, $youtubeUrl);
        if ($duplicateResult['is_duplicate']) {
            return $duplicateResult;
        }

        // Download and process the video
        return $this->downloadAndProcess($libraryItem, $youtubeUrl);
    }

    /**
     * Download and process YouTube video.
     */
    private function downloadAndProcess(LibraryItem $libraryItem, string $youtubeUrl): array
    {
        $tempDir = 'temp-youtube/'.uniqid();

        try {
            // Download the video
            $downloadedFile = $this->downloader->downloadAudio($youtubeUrl, $tempDir);

            if (! $downloadedFile) {
                $libraryItem->delete();

                return [
                    'success' => false,
                    'error' => 'Failed to download YouTube video',
                ];
            }

            // Extract metadata
            $metadata = $this->metadataExtractor->extractMetadata($youtubeUrl);

            // Process the downloaded file
            $result = $this->fileProcessor->processFile($downloadedFile, $youtubeUrl, $libraryItem);

            // Update library item with metadata
            $this->fileProcessor->updateLibraryItemWithMetadata($libraryItem, $metadata);

            // Update library item with media file and status
            $libraryItem->media_file_id = $result['media_file']->id;
            $libraryItem->update([
                'processing_status' => ProcessingStatusType::COMPLETED,
                'processing_completed_at' => now(),
            ]);

            return [
                'success' => true,
                'is_duplicate' => $result['is_duplicate'],
                'message' => $result['message'],
            ];

        } catch (\Exception $e) {
            Log::error('YouTube processing failed', [
                'library_item_id' => $libraryItem->id,
                'youtube_url' => $youtubeUrl,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            $libraryItem->update([
                'processing_status' => ProcessingStatusType::FAILED,
                'processing_completed_at' => now(),
                'processing_error' => 'YouTube processing failed: '.$e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'YouTube processing failed: '.$e->getMessage(),
            ];
        } finally {
            // Clean up temp directory
            $this->downloader->cleanupTempDirectory($tempDir);
        }
    }

    /**
     * Mark library item as processing.
     */
    private function markAsProcessing(LibraryItem $libraryItem): void
    {
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::PROCESSING,
            'processing_started_at' => now(),
            'processing_error' => null,
        ]);
    }
}
