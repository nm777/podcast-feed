<?php

namespace App\Services\YouTube;

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class YouTubeFileProcessor
{
    public function __construct(
        private UnifiedDuplicateProcessor $duplicateProcessor,
    ) {}

    /**
     * Process downloaded YouTube file and create MediaFile record.
     */
    public function processFile(string $downloadedFile, string $youtubeUrl, LibraryItem $libraryItem): array
    {
        $fullPath = Storage::disk('public')->path($downloadedFile);
        $fileHash = hash_file('sha256', $fullPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $finalPath = 'media/'.$fileHash.'.'.$extension;

        Log::info('Calculated file info', [
            'library_item_id' => $libraryItem->id,
            'full_path' => $fullPath,
            'file_hash' => $fileHash,
            'extension' => $extension,
            'final_path' => $finalPath,
        ]);

        // Check for duplicates
        $duplicateResult = $this->duplicateProcessor->processFileDuplicate($libraryItem, $downloadedFile);

        if ($duplicateResult['is_duplicate']) {
            // Clean up temp file
            Storage::disk('public')->delete($downloadedFile);

            return $duplicateResult;
        }

        // Move file to final location using hash
        $moveSuccess = Storage::disk('public')->move($downloadedFile, $finalPath);

        Log::info('File move completed', [
            'library_item_id' => $libraryItem->id,
            'move_success' => $moveSuccess,
            'to_exists' => Storage::disk('public')->exists($finalPath),
            'final_path' => $finalPath,
        ]);

        $mimeType = File::mimeType(Storage::disk('public')->path($finalPath));
        $fileSize = File::size(Storage::disk('public')->path($finalPath));

        Log::info('Creating media file record', [
            'library_item_id' => $libraryItem->id,
            'file_path' => $finalPath,
            'file_hash' => $fileHash,
            'mime_type' => $mimeType,
            'filesize' => $fileSize,
            'source_url' => $youtubeUrl,
        ]);

        $mediaFile = MediaFile::create([
            'user_id' => $libraryItem->user_id,
            'file_path' => $finalPath,
            'file_hash' => $fileHash,
            'mime_type' => $mimeType,
            'filesize' => $fileSize,
            'source_url' => $youtubeUrl,
        ]);

        return [
            'is_duplicate' => false,
            'media_file' => $mediaFile,
            'message' => 'YouTube video processed successfully.',
        ];
    }

    /**
     * Update library item with YouTube metadata.
     */
    public function updateLibraryItemWithMetadata(LibraryItem $libraryItem, array $metadata): void
    {
        if ($metadata && ! $libraryItem->title) {
            $libraryItem->title = $metadata['title'] ?? $libraryItem->title;
            $libraryItem->description = $metadata['description'] ?? $libraryItem->description;
        }
    }
}
