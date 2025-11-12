<?php

namespace App\Services\SourceProcessors;

use App\Http\Requests\LibraryItemRequest;
use App\Jobs\ProcessMediaFile;
use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UnifiedSourceProcessor
{
    public function __construct(
        private UnifiedDuplicateProcessor $duplicateProcessor,
        private SourceStrategyInterface $strategy
    ) {}

    /**
     * Process source using unified logic with strategy pattern.
     */
    public function process(LibraryItemRequest $request, array $validated, string $sourceType, ?string $sourceUrl): array
    {
        // Validate source using strategy
        $this->strategy->validate($sourceUrl);

        // Handle file upload for new items
        if ($sourceType === 'upload') {
            return $this->handleFileUpload($request, $validated, $sourceType);
        }

        // Handle URL sources (YouTube, regular URL)
        return $this->handleUrlSource($validated, $sourceType, $sourceUrl);
    }

    /**
     * Handle file upload processing.
     */
    private function handleFileUpload(LibraryItemRequest $request, array $validated, string $sourceType): array
    {
        $file = $request->file('file');
        $tempPath = $file->store('temp-uploads', 'public');

        // Create temporary library item for duplicate checking
        $tempLibraryItem = $this->createLibraryItemFromValidated($validated, $sourceType);

        // Check for file duplicates
        $duplicateResult = $this->duplicateProcessor->processFileDuplicate($tempLibraryItem, $tempPath);

        if ($duplicateResult['media_file']) {
            // Clean up temp file
            Storage::disk('public')->delete($tempPath);

            // Delete temporary library item
            $tempLibraryItem->delete();

            // Create final library item with user-provided data and existing media file
            $libraryItem = $this->updateLibraryItemFromValidated($duplicateResult['media_file'], $validated, $sourceType);

            return [$libraryItem, $this->strategy->getSuccessMessage($duplicateResult['is_duplicate'])];
        }

        // Delete temporary library item and create new one for processing
        $tempLibraryItem->delete();
        $libraryItem = $this->createLibraryItemFromValidated($validated, $sourceType, null, [
            'file_path' => $tempPath,
            'file_hash' => hash('sha256', Storage::disk('public')->get($tempPath)),
            'mime_type' => $file->getMimeType(),
            'filesize' => $file->getSize(),
        ]);

        // Process new file
        ProcessMediaFile::dispatch($libraryItem, null, $tempPath);

        return [$libraryItem, $this->strategy->getProcessingMessage()];
    }

    /**
     * Handle URL source processing.
     */
    private function handleUrlSource(array $validated, string $sourceType, ?string $sourceUrl): array
    {
        $userId = auth()->id();

        // Check for user duplicates first (before creating any library item)
        $userDuplicate = DuplicateDetectionService::findUrlDuplicateForUser($sourceUrl, $userId);

        if ($userDuplicate) {
            // User already has this URL - update existing library item with new data, mark as duplicate, and return duplicate message
            $userDuplicate->update([
                'title' => $validated['title'] ?? $userDuplicate->title,
                'description' => $validated['description'] ?? $userDuplicate->description,
                'is_duplicate' => true,
            ]);

            return [$userDuplicate, $this->strategy->getSuccessMessage(true)];
        }

        // Check for user media file only (edge case where user has MediaFile but no LibraryItem)
        $globalDuplicate = DuplicateDetectionService::findGlobalUrlDuplicate($sourceUrl);
        if ($globalDuplicate && $globalDuplicate->user_id === $userId && ! DuplicateDetectionService::findUrlDuplicateForUser($sourceUrl, $userId)) {
            // Create library item linking to existing user media file
            $libraryItem = $this->updateLibraryItemFromValidated($globalDuplicate, $validated, $sourceType, $sourceUrl);

            return [$libraryItem, $this->strategy->getSuccessMessage(true)];
        }

        // Check for global duplicates from other users
        if ($globalDuplicate && $globalDuplicate->user_id !== $userId) {
            // Create library item linking to global duplicate and mark as cross-user duplicate
            $libraryItem = $this->updateLibraryItemFromValidated($globalDuplicate, $validated, $sourceType, $sourceUrl);
            $libraryItem->update(['is_duplicate' => true]);

            return [$libraryItem, $this->strategy->getSuccessMessage(true)];
        }

        // No duplicates found - create new library item and process
        $libraryItem = $this->createLibraryItemFromValidated($validated, $sourceType, $sourceUrl);
        $this->strategy->processNewSource($libraryItem, $sourceUrl);

        return [$libraryItem, $this->strategy->getProcessingMessage()];
    }

    /**
     * Create library item from validated data.
     */
    private function createLibraryItemFromValidated(array $validated, string $sourceType, ?string $sourceUrl = null, array $mediaFileData = []): LibraryItem
    {
        return LibraryItem::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => Auth::user()->id,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'processing_status' => ProcessingStatusType::PENDING,
        ] + $mediaFileData);
    }

    /**
     * Update library item with validated data while preserving existing media file relationship.
     */
    private function updateLibraryItemFromValidated($mediaFile, array $validated, string $sourceType, ?string $sourceUrl = null): LibraryItem
    {
        $libraryItem = LibraryItem::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => Auth::user()->id,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'media_file_id' => $mediaFile->id,
            'is_duplicate' => $mediaFile->user_id === Auth::user()->id,
            'duplicate_detected_at' => $mediaFile->user_id === Auth::user()->id ? now() : null,
            'processing_status' => ProcessingStatusType::COMPLETED,
            'processing_completed_at' => now(),
        ]);

        return $libraryItem;
    }
}
