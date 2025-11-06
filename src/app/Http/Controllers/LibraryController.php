<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryItemRequest;
use App\Jobs\CleanupDuplicateLibraryItem;
use App\Jobs\ProcessMediaFile;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LibraryController extends Controller
{
    public function index()
    {
        $libraryItems = auth()->user()->libraryItems()
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

        $mediaFileId = null;
        $message = '';

        // Check if URL already exists in our system
        if ($request->filled('url')) {
            $existingMediaFile = MediaFile::findBySourceUrl($request->input('url'));

            if ($existingMediaFile) {
                $mediaFileId = $existingMediaFile->id;
                $message = 'Duplicate URL detected. This file already exists in your library and will be removed automatically in 5 minutes.';
            }
        }

        $libraryItem = LibraryItem::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'source_type' => $request->hasFile('file') ? 'upload' : 'url',
            'source_url' => $request->input('url'),
            'media_file_id' => $mediaFileId,
            'is_duplicate' => $mediaFileId ? true : false,
            'duplicate_detected_at' => $mediaFileId ? now() : null,
        ]);

        if ($mediaFileId) {
            // File already exists, no processing needed
            $message = $message ?: 'Media file already exists. Added to your library.';

            // Schedule cleanup for duplicate URL entries
            if ($request->filled('url')) {
                CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));
            }
        } elseif ($request->hasFile('file')) {
            $file = $request->file('file');
            $tempPath = $file->store('temp-uploads');
            $fullTempPath = Storage::disk('local')->path($tempPath);

            // Check for duplicate by file hash
            $existingMediaFile = MediaFile::isDuplicate($fullTempPath);

            if ($existingMediaFile) {
                // Clean up temp file
                Storage::disk('local')->delete($tempPath);

                // Link to existing media file and mark as duplicate
                $libraryItem->media_file_id = $existingMediaFile->id;
                $libraryItem->is_duplicate = true;
                $libraryItem->duplicate_detected_at = now();
                $libraryItem->save();

                $message = 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.';

                // Schedule cleanup
                CleanupDuplicateLibraryItem::dispatch($libraryItem)->delay(now()->addMinutes(5));
            } else {
                // Process the new file
                ProcessMediaFile::dispatch($libraryItem, null, $tempPath);
                $message = 'Media file uploaded successfully. Processing...';
            }
        } elseif ($request->filled('url')) {
            ProcessMediaFile::dispatch($libraryItem, $request->input('url'), null);
            $message = 'Media file URL added successfully. Downloading and processing...';
        }

        return redirect()->route('library.index')
            ->with('success', $message);
    }

    public function destroy($id)
    {
        $libraryItem = LibraryItem::findOrFail($id);

        // Ensure user can only delete their own library items
        if ($libraryItem->user_id !== auth()->id()) {
            abort(403);
        }

        $mediaFile = $libraryItem->mediaFile;
        $libraryItem->delete();

        // Check if this was the last reference to the media file
        if ($mediaFile && $mediaFile->libraryItems()->count() === 0) {
            Storage::disk('local')->delete($mediaFile->file_path);
            $mediaFile->delete();
        }

        return redirect()->route('library.index')
            ->with('success', 'Media file removed from your library.');
    }
}
