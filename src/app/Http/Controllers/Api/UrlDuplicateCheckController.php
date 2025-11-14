<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UrlDuplicateCheckRequest;
use App\Http\Resources\MediaFileResource;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Auth;

class UrlDuplicateCheckController extends Controller
{
    public function check(UrlDuplicateCheckRequest $request)
    {
        $validated = $request->validated();
        $url = $validated['url'];

        $existingLibraryItem = LibraryItem::findBySourceUrlForUser($url, Auth::user()->id);
        $existingMediaFile = MediaFile::findBySourceUrl($url);

        // Check if user has either a library item or a media file with this URL
        $isDuplicate = $existingLibraryItem || ($existingMediaFile && $existingMediaFile->user_id === Auth::user()->id);
        $mediaFile = $existingLibraryItem?->mediaFile ?? ($existingMediaFile && $existingMediaFile->user_id === Auth::user()->id ? $existingMediaFile : null);

        return response()->json([
            'is_duplicate' => $isDuplicate ? true : false,
            'existing_file' => $mediaFile ? MediaFileResource::make($mediaFile) : null,
        ]);
    }
}
