<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryItemRequest;
use App\Jobs\CleanupDuplicateLibraryItem;
use App\Jobs\ProcessMediaFile;
use App\Jobs\ProcessYouTubeAudio;
use App\Models\LibraryItem;
use App\ProcessingStatusType;
use App\Services\DuplicateDetectionService;
use App\Services\YouTubeUrlValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LibraryController extends Controller
{
    public function index()
    {
        $libraryItems = Auth::user()->libraryItems()
            ->with('mediaFile')
            ->latest()
            ->get();

        return Inertia::render('Library/Index', [
            'libraryItems' => $libraryItems,
        ]);
    }

    public function store(LibraryItemRequest $request)
    {
        $validated = $request->validated();

        [$sourceType, $sourceUrl] = $this->getSourceTypeAndUrl($request);

        if ($redirectResponse = $this->validateYouTubeUrl($sourceType, $sourceUrl)) {
            return $redirectResponse;
        }

        if ($sourceType === 'upload') {
            [, $message] = $this->handleUploadSource($request, $validated, $sourceType, $sourceUrl);
        } else {
            [, $message] = $this->handleUrlSource($request, $validated, $sourceType, $sourceUrl);
        }

        return redirect()->route('library.index')
            ->with('success', $message);
    }

    public function destroy($id)
    {
        $libraryItem = LibraryItem::findOrFail($id);

        // Ensure user can only delete their own library items
        if ($libraryItem->user_id !== Auth::user()->id) {
            abort(403);
        }

        $mediaFile = $libraryItem->mediaFile;
        $libraryItem->delete();

        // Check if this was the last reference to the media file
        if ($mediaFile && $mediaFile->libraryItems()->count() === 0) {
            Storage::disk('public')->delete($mediaFile->file_path);
            $mediaFile->delete();
        }

        return redirect()->route('library.index')
            ->with('success', 'Media file removed from your library.');
    }

    /**
     * Get the source type and URL from the request, handling backward compatibility.
     */
    private function getSourceTypeAndUrl(LibraryItemRequest $request): array
    {
        $sourceType = $request->input('source_type', $request->hasFile('file') ? 'upload' : 'url');
        $sourceUrl = $request->input('source_url', $request->input('url'));

        return [$sourceType, $sourceUrl];
    }

    /**
     * Validate YouTube URL and return redirect response if invalid.
     */
    private function validateYouTubeUrl(string $sourceType, ?string $sourceUrl): ?\Illuminate\Http\RedirectResponse
    {
        if ($sourceType === 'youtube' && ! YouTubeUrlValidator::isValidYouTubeUrl($sourceUrl)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['source_url' => 'Invalid YouTube URL']);
        }

        return null;
    }

    /**
     * Handle file upload source type.
     */
    private function handleUploadSource(LibraryItemRequest $request, array $validated, string $sourceType, ?string $sourceUrl): array
    {
        $file = $request->file('file');
        $tempPath = $file->store('temp-uploads', 'public');

        // Analyze file for duplicates using centralized service
        $duplicateAnalysis = DuplicateDetectionService::analyzeFileUpload($tempPath, Auth::user()->id);

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            // Clean up temp file
            Storage::disk('public')->delete($tempPath);

            // Create library item with duplicate flag
            $libraryItem = $this->createLibraryItem($validated, $sourceType, $sourceUrl, [
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
            $libraryItem = $this->createLibraryItem($validated, $sourceType, $sourceUrl, [
                'media_file_id' => null,
                'is_duplicate' => false,
                'processing_status' => ProcessingStatusType::PENDING,
            ]);

            // Process new file (deduplication will be handled in the job)
            ProcessMediaFile::dispatch($libraryItem, null, $tempPath);
            $message = 'Media file uploaded successfully. Processing...';
        }

        return [$libraryItem, $message];
    }

    /**
     * Handle URL and YouTube source types.
     */
    private function handleUrlSource(LibraryItemRequest $request, array $validated, string $sourceType, ?string $sourceUrl): array
    {
        $mediaFileId = null;
        $message = '';

        // Analyze URL for duplicates using centralized service
        $duplicateAnalysis = DuplicateDetectionService::analyzeUrlSource($sourceUrl, Auth::user()->id);

        if ($duplicateAnalysis['should_link_to_user_duplicate']) {
            // User already has this URL in their library - true duplicate
            $mediaFileId = $duplicateAnalysis['user_duplicate_library_item']->media_file_id;
            $message = 'Duplicate URL detected. This file already exists in your library and will be removed automatically in 5 minutes.';
        } elseif ($duplicateAnalysis['should_link_to_user_media_file']) {
            // User has a media file with this URL but no library item (edge case)
            $mediaFileId = $duplicateAnalysis['global_duplicate_media_file']->id;
            $message = 'Duplicate URL detected. This file already exists in your library and will be removed automatically in 5 minutes.';
        }
        // For cross-user scenarios, we don't link to existing media files in LibraryController
        // Each user gets their own media file even for same URLs
        // Cross-user linking is handled in ProcessMediaFile job

        $libraryItem = $this->createLibraryItem($validated, $sourceType, $sourceUrl, [
            'media_file_id' => $mediaFileId,
            'is_duplicate' => $duplicateAnalysis['is_user_duplicate'],
            'duplicate_detected_at' => $duplicateAnalysis['is_user_duplicate'] ? now() : null,
            'processing_status' => $mediaFileId ? ProcessingStatusType::COMPLETED : ProcessingStatusType::PENDING,
            'processing_completed_at' => $mediaFileId ? now() : null,
        ]);

        if ($mediaFileId) {
            // File already exists, no processing needed
            $message = $message ?: 'Media file already exists. Added to your library.';

            // Schedule cleanup for duplicate URL entries
            if ($sourceUrl && ($duplicateAnalysis['is_user_duplicate'] || $duplicateAnalysis['user_media_file_only'])) {
                CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));
            }
        } elseif ($sourceType === 'url') {
            ProcessMediaFile::dispatch($libraryItem, $sourceUrl, null);
            $message = 'Media file URL added successfully. Downloading and processing...';
        } elseif ($sourceType === 'youtube') {
            ProcessYouTubeAudio::dispatch($libraryItem, $sourceUrl);
            $message = 'YouTube video added successfully. Extracting audio...';
        }

        return [$libraryItem, $message];
    }

    /**
     * Create a library item with the given attributes.
     */
    private function createLibraryItem(array $validated, string $sourceType, ?string $sourceUrl, array $attributes): LibraryItem
    {
        return LibraryItem::create(array_merge([
            'user_id' => Auth::user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
        ], $attributes));
    }
}
