<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LibraryItemRequest;
use App\Http\Resources\LibraryItemResource;
use App\Jobs\ProcessMediaFile;
use App\Jobs\ProcessYouTubeAudio;
use App\Models\LibraryItem;
use App\Services\SourceProcessors\SourceProcessorFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LibraryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $libraryItems = LibraryItem::where('user_id', Auth::user()->id)->get();

        return LibraryItemResource::collection($libraryItems);
    }

    /**
     * Store a newly created library item.
     */
    public function store(LibraryItemRequest $request)
    {
        $validated = $request->validated();

        // Additional validation for YouTube URLs using factory
        $sourceType = $validated['source_type'];
        $sourceUrl = $validated['source_url'] ?? null;

        if ($redirectResponse = SourceProcessorFactory::validate($sourceType, $sourceUrl)) {
            throw new \App\Exceptions\ValidationException(
                $redirectResponse->getSession()->get('errors', []),
                'Source URL validation failed'
            );
        }

        $libraryItem = LibraryItem::create([
            'user_id' => Auth::user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
        ]);

        if ($sourceType === 'upload') {
            $filePath = $request->file('file')->store('uploads');
            ProcessMediaFile::dispatch($libraryItem, null, $filePath);
        } elseif ($sourceType === 'url') {
            ProcessMediaFile::dispatch($libraryItem, $sourceUrl);
        } elseif ($sourceType === 'youtube') {
            ProcessYouTubeAudio::dispatch($libraryItem, $sourceUrl);
        }

        return (new LibraryItemResource($libraryItem))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
