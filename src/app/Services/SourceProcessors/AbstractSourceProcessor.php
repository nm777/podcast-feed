<?php

namespace App\Services\SourceProcessors;

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\ProcessingStatusType;
use Illuminate\Database\DatabaseManager;

abstract class AbstractSourceProcessor implements SourceProcessorInterface
{
    public function __construct(
        protected DatabaseManager $db,
        protected \App\Services\DuplicateDetectionService $duplicateDetection
    ) {}

    /**
     * Create a library item with basic media file information.
     */
    protected function createLibraryItem(array $validated, User $user, string $sourceType, ?string $sourceUrl = null, array $mediaFileData = []): LibraryItem
    {
        return $this->db->transaction(function () use ($validated, $user, $sourceType, $sourceUrl, $mediaFileData) {
            // Create library item
            $libraryItem = LibraryItem::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'user_id' => $user->id,
                'source_type' => $sourceType,
                'source_url' => $sourceUrl,
                'processing_status' => ProcessingStatusType::PENDING,
            ]);

            // Only create media file if data is provided (for uploads)
            if (! empty($mediaFileData)) {
                MediaFile::create(array_merge([
                    'library_item_id' => $libraryItem->id,
                    'user_id' => $user->id,
                ], $mediaFileData));
            }

            return $libraryItem;
        });
    }

    /**
     * Extract title from a filename.
     */
    protected function extractTitleFromFilename(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }
}
