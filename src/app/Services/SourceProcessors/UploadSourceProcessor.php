<?php

namespace App\Services\SourceProcessors;

use App\Http\Requests\LibraryItemRequest;
use App\Jobs\CleanupDuplicateLibraryItem;
use App\Jobs\ProcessMediaFile;
use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UploadSourceProcessor extends AbstractSourceProcessor
{
    public function process(LibraryItemRequest $request, array $validated, string $sourceType, ?string $sourceUrl): array
    {
        $file = $request->file('file');
        $tempPath = $file->store('temp-uploads', 'public');

        // Analyze file for duplicates using centralized service
        $duplicateAnalysis = DuplicateDetectionService::analyzeFileUpload($tempPath, Auth::user()->id);

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            // Clean up temp file
            Storage::disk('public')->delete($tempPath);

            // Create library item with duplicate flag
            $libraryItem = LibraryItem::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'user_id' => Auth::user()->id,
                'source_type' => $sourceType,
                'media_file_id' => $duplicateAnalysis['user_duplicate_media_file']->id,
                'is_duplicate' => true,
                'duplicate_detected_at' => now(),
                'processing_status' => ProcessingStatusType::COMPLETED,
                'processing_completed_at' => now(),
            ]);

            $message = 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.';

            // Schedule cleanup
            CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));
        } else {
            // Create library item for new file processing
            $libraryItem = $this->createLibraryItem(
                $validated,
                Auth::user(),
                $sourceType,
                $sourceUrl,
                [
                    'file_path' => $tempPath,
                    'file_hash' => hash('sha256', $tempPath),
                    'mime_type' => $file->getMimeType(),
                    'filesize' => $file->getSize(),
                ]
            );

            // Process new file (deduplication will be handled in the job)
            ProcessMediaFile::dispatch($libraryItem, null, $tempPath);
            $message = 'Media file uploaded successfully. Processing...';
        }

        return [$libraryItem, $message];
    }
}
