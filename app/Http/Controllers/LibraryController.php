<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMediaFile;
use App\Models\LibraryItem;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:mp3,mp4,m4a,wav,ogg|max:512000',
        ]);

        $libraryItem = LibraryItem::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'source_type' => 'upload',
        ]);

        $filePath = $request->file('file')->store('temp-uploads');
        ProcessMediaFile::dispatch($libraryItem, null, $filePath);

        return redirect()->route('library.index')
            ->with('success', 'Media file uploaded successfully. Processing...');
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
