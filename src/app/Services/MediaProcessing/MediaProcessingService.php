<?php

namespace App\Services\MediaProcessing;

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\ProcessingStatusType;
use Illuminate\Support\Facades\Storage;

class MediaProcessingService
{
    public function __construct(
        private MediaDownloader $downloader,
        private MediaValidator $validator,
        private MediaStorageManager $storageManager,
        private UnifiedDuplicateProcessor $duplicateProcessor
    ) {}

    /**
     * Process media file from URL.
     */
    public function processFromUrl(LibraryItem $libraryItem, string $sourceUrl): array
    {
        try {
            // Mark as processing
            $this->markAsProcessing($libraryItem);

            // Check for URL duplicates first
            $duplicateResult = $this->duplicateProcessor->processUrlDuplicate($libraryItem, $sourceUrl);
            if ($duplicateResult['media_file']) {
                return $duplicateResult;
            }

            // Download the file
            $content = $this->downloader->downloadFromUrl($sourceUrl);

            // Store to temporary location for processing
            $tempPath = 'temp-uploads/'.uniqid().'_'.basename(parse_url($sourceUrl, PHP_URL_PATH) ?: 'download');
            Storage::disk('public')->put($tempPath, $content);

            // Process the downloaded file
            return $this->processFromFile($libraryItem, $tempPath, $sourceUrl);

        } catch (\Exception $e) {
            return $this->handleProcessingError($libraryItem, $e);
        }
    }

    /**
     * Process media file from uploaded file path.
     */
    public function processFromFile(LibraryItem $libraryItem, string $filePath, ?string $sourceUrl = null): array
    {
        try {
            // Mark as processing if not already
            if ($libraryItem->processing_status !== ProcessingStatusType::PROCESSING) {
                $this->markAsProcessing($libraryItem);
            }

            // Verify file exists
            if (! $this->storageManager->fileExists($filePath)) {
                throw new \Exception('Temp file not found or inaccessible');
            }

            // Check for duplicates
            $duplicateResult = $this->duplicateProcessor->processFileDuplicate($libraryItem, $filePath);
            if ($duplicateResult['media_file']) {
                return $duplicateResult;
            }

            // Validate and get file metadata
            $fullPath = Storage::disk('public')->path($filePath);
            $metadata = $this->validator->validate($fullPath);

            // Move file to permanent location
            $fileData = $this->storageManager->moveTempFile($filePath, $sourceUrl);
            $fileData = array_merge($fileData, $metadata);

            // Create media file record
            $mediaFile = MediaFile::create([
                'user_id' => $libraryItem->user_id,
                'file_path' => $fileData['file_path'],
                'file_hash' => $fileData['file_hash'],
                'mime_type' => $fileData['mime_type'],
                'filesize' => $fileData['filesize'],
                'source_url' => $sourceUrl,
            ]);

            // Link to library item
            $libraryItem->media_file_id = $mediaFile->id;
            $libraryItem->update([
                'processing_status' => ProcessingStatusType::COMPLETED,
                'processing_completed_at' => now(),
            ]);

            return [
                'is_duplicate' => false,
                'media_file' => $mediaFile,
                'message' => 'Media file processed successfully.',
            ];

        } catch (\Exception $e) {
            return $this->handleProcessingError($libraryItem, $e);
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

    /**
     * Handle processing errors.
     */
    private function handleProcessingError(LibraryItem $libraryItem, \Exception $e): array
    {
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::FAILED,
            'processing_completed_at' => now(),
            'processing_error' => 'Processing failed: '.$e->getMessage(),
        ]);

        return [
            'is_duplicate' => false,
            'media_file' => null,
            'error' => $e->getMessage(),
            'message' => 'Processing failed: '.$e->getMessage(),
        ];
    }
}
