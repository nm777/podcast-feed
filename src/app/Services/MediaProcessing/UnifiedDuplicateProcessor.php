<?php

namespace App\Services\MediaProcessing;

use App\Jobs\CleanupDuplicateLibraryItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use Illuminate\Support\Facades\Log;

class UnifiedDuplicateProcessor
{
    /**
     * Process duplicate detection and handling for URL sources.
     */
    public function processUrlDuplicate(LibraryItem $libraryItem, string $sourceUrl): array
    {
        $duplicateAnalysis = DuplicateDetectionService::analyzeUrlSource(
            $sourceUrl,
            $libraryItem->user_id,
            $libraryItem->id
        );

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            return $this->handleUserUrlDuplicate($libraryItem, $duplicateAnalysis);
        }

        if ($duplicateAnalysis['should_link_to_user_media_file']) {
            return $this->handleUserMediaFileOnly($libraryItem, $duplicateAnalysis);
        }

        if ($duplicateAnalysis['should_link_to_global_duplicate']) {
            return $this->handleGlobalUrlDuplicate($libraryItem, $duplicateAnalysis, $sourceUrl);
        }

        return ['success' => true, 'is_duplicate' => false, 'media_file' => null];
    }

    /**
     * Process duplicate detection and handling for file uploads.
     */
    public function processFileDuplicate(LibraryItem $libraryItem, string $filePath): array
    {
        $duplicateAnalysis = DuplicateDetectionService::analyzeFileUpload($filePath, $libraryItem->user_id);

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            return $this->handleUserFileDuplicate($libraryItem, $duplicateAnalysis, $filePath);
        }

        if ($duplicateAnalysis['should_link_to_global_duplicate']) {
            return $this->handleGlobalFileDuplicate($libraryItem, $duplicateAnalysis, $filePath);
        }

        return ['success' => true, 'is_duplicate' => false, 'media_file' => null];
    }

    /**
     * Handle user duplicate for URL sources.
     */
    private function handleUserUrlDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis): array
    {
        $existingLibraryItem = $duplicateAnalysis['user_duplicate_library_item'];

        Log::info('Found existing library item for URL', [
            'library_item_id' => $libraryItem->id,
            'existing_library_item_id' => $existingLibraryItem->id,
            'existing_media_file_id' => $existingLibraryItem->media_file_id,
        ]);

        // Link to existing media file and mark as duplicate
        $libraryItem->media_file_id = $existingLibraryItem->media_file_id;
        $libraryItem->is_duplicate = true;
        $libraryItem->duplicate_detected_at = now();
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        // Schedule cleanup of this duplicate entry
        CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));

        // Store flash message for user notification
        session()->flash('warning', 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.');

        return [
            'success' => true,
            'is_duplicate' => true,
            'media_file' => $existingLibraryItem->mediaFile,
            'message' => 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.',
        ];
    }

    /**
     * Handle case where user has MediaFile but no LibraryItem for URL.
     */
    private function handleUserMediaFileOnly(LibraryItem $libraryItem, array $duplicateAnalysis): array
    {
        $mediaFile = $duplicateAnalysis['global_duplicate_media_file'];

        Log::info('Found existing media file for user (no library item)', [
            'library_item_id' => $libraryItem->id,
            'media_file_id' => $mediaFile->id,
        ]);

        // Link to existing media file
        $libraryItem->media_file_id = $mediaFile->id;
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        return [
            'success' => true,
            'is_duplicate' => false,
            'media_file' => $mediaFile,
            'message' => 'File already exists in your library. Linked to existing media file.',
        ];
    }

    /**
     * Handle global duplicate for URL sources.
     */
    private function handleGlobalUrlDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $sourceUrl): array
    {
        $globalMediaFile = $duplicateAnalysis['global_duplicate_media_file'];

        Log::info('Found existing media file from different user for URL', [
            'library_item_id' => $libraryItem->id,
            'existing_media_file_id' => $globalMediaFile->id,
            'existing_user_id' => $globalMediaFile->user_id,
            'current_user_id' => $libraryItem->user_id,
            'source_url' => $sourceUrl,
        ]);

        // Link to existing media file from different user (cross-user sharing)
        $libraryItem->media_file_id = $globalMediaFile->id;
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        // Store flash message for user notification
        session()->flash('info', 'Linked to existing media file.');

        return [
            'success' => true,
            'is_duplicate' => false,
            'media_file' => $globalMediaFile,
            'message' => 'File already exists in system. Linked to existing media file.',
        ];
    }

    /**
     * Handle user duplicate for file uploads.
     */
    private function handleUserFileDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $filePath): array
    {
        $userDuplicateMediaFile = $duplicateAnalysis['user_duplicate_media_file'];

        // Update source URL if this is first time we've seen it from a URL
        if ($libraryItem->source_url && ! $userDuplicateMediaFile->source_url) {
            $userDuplicateMediaFile->source_url = $libraryItem->source_url;
            $userDuplicateMediaFile->save();
        }

        // Mark this library item as a duplicate
        $libraryItem->media_file_id = $userDuplicateMediaFile->id;
        $libraryItem->is_duplicate = true;
        $libraryItem->duplicate_detected_at = now();
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        // Schedule cleanup of this duplicate entry
        CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));

        // Store flash message for user notification
        session()->flash('warning', 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.');

        return [
            'success' => true,
            'is_duplicate' => true,
            'media_file' => $userDuplicateMediaFile,
            'message' => 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.',
        ];
    }

    /**
     * Handle global duplicate for file uploads.
     */
    private function handleGlobalFileDuplicate(LibraryItem $libraryItem, array $duplicateAnalysis, string $filePath): array
    {
        $globalDuplicateMediaFile = $duplicateAnalysis['global_duplicate_media_file'];

        // Don't mark as duplicate since this is a different user's file
        $libraryItem->media_file_id = $globalDuplicateMediaFile->id;
        $libraryItem->update([
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        // Store flash message for user notification
        session()->flash('info', 'File already exists in system. Linked to existing media file.');

        return [
            'success' => true,
            'is_duplicate' => false,
            'media_file' => $globalDuplicateMediaFile,
            'message' => 'File already exists in system. Linked to existing media file.',
        ];
    }
}
