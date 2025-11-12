<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryItemRequest;
use App\Models\LibraryItem;
use App\Services\SourceProcessors\SourceProcessorFactory;
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

        if ($redirectResponse = SourceProcessorFactory::validate($sourceType, $sourceUrl)) {
            return $redirectResponse;
        }

        // Use strategy pattern to process different source types
        $processor = SourceProcessorFactory::create($sourceType);
        [$libraryItem, $message] = $processor->process($request, $validated, $sourceType, $sourceUrl);

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
     * Get source type and URL from request, handling backward compatibility.
     */
    private function getSourceTypeAndUrl(LibraryItemRequest $request): array
    {
        $sourceType = $request->input('source_type', $request->hasFile('file') ? 'upload' : 'url');
        $sourceUrl = $request->input('source_url', $request->input('url'));

        return [$sourceType, $sourceUrl];
    }
}
