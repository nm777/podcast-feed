<?php

namespace App\Services\SourceProcessors;

use App\Http\Requests\LibraryItemRequest;
use App\Jobs\ProcessMediaFile;
use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use Illuminate\Support\Facades\Auth;

class UrlSourceProcessor extends AbstractSourceProcessor
{
    public function process(LibraryItemRequest $request, array $validated, string $sourceType, ?string $sourceUrl): array
    {
        // Check for URL duplicates
        $duplicateAnalysis = DuplicateDetectionService::analyzeUrlSource($sourceUrl, Auth::user()->id);

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            // Link to existing user library item
            $libraryItem = LibraryItem::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'user_id' => Auth::user()->id,
                'source_type' => $sourceType,
                'source_url' => $sourceUrl,
                'media_file_id' => $duplicateAnalysis['user_duplicate_library_item']->media_file_id,
                'is_duplicate' => true,
                'duplicate_detected_at' => now(),
                'processing_status' => ProcessingStatusType::COMPLETED,
                'processing_completed_at' => now(),
            ]);

            $message = 'This URL has already been processed. The existing media file has been linked to this library item.';
        } elseif ($duplicateAnalysis['should_link_to_user_media_file']) {
            // Link to existing user media file (no library item)
            $libraryItem = LibraryItem::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'user_id' => Auth::user()->id,
                'source_type' => $sourceType,
                'source_url' => $sourceUrl,
                'media_file_id' => $duplicateAnalysis['global_duplicate_media_file']->id,
                'is_duplicate' => true,
                'duplicate_detected_at' => now(),
                'processing_status' => ProcessingStatusType::COMPLETED,
                'processing_completed_at' => now(),
            ]);

            $message = 'This URL has already been processed. The existing media file has been linked to this library item.';
        } elseif ($duplicateAnalysis['should_link_to_global_duplicate']) {
            // Link to existing global media file
            $libraryItem = LibraryItem::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'user_id' => Auth::user()->id,
                'source_type' => $sourceType,
                'source_url' => $sourceUrl,
                'media_file_id' => $duplicateAnalysis['global_duplicate_media_file']->id,
                'is_duplicate' => true,
                'duplicate_detected_at' => now(),
                'processing_status' => ProcessingStatusType::COMPLETED,
                'processing_completed_at' => now(),
            ]);

            $message = 'This URL has already been processed. The existing media file has been linked to this library item.';
        } else {
            // Create library item for new URL processing
            $libraryItem = $this->createLibraryItem(
                $validated,
                Auth::user(),
                $sourceType,
                $sourceUrl
            );

            // Process new URL
            ProcessMediaFile::dispatch($libraryItem, $sourceUrl);
            $message = 'URL added successfully. Processing...';
        }

        return [$libraryItem, $message];
    }
}
