<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMediaFile;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LibraryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return LibraryItem::where('user_id', auth()->id())->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'source_type' => 'required|in:upload,url,youtube',
            'file' => 'required_if:source_type,upload|file|mimes:mp3,mp4,m4a|max:512000',
            'source_url' => 'required_if:source_type,url,youtube|url',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $libraryItem = LibraryItem::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'source_type' => $request->source_type,
            'source_url' => $request->source_url,
        ]);

        if ($request->source_type === 'upload') {
            $filePath = $request->file('file')->store('uploads');
            ProcessMediaFile::dispatch($libraryItem, null, $filePath);
        } elseif ($request->source_type === 'url') {
            ProcessMediaFile::dispatch($libraryItem, $request->source_url);
        }

        return response()->json($libraryItem, 201);
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
