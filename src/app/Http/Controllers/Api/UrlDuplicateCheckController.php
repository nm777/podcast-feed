<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UrlDuplicateCheckController extends Controller
{
    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }

        $url = $request->input('url');
        $existingMediaFile = MediaFile::findBySourceUrl($url);

        return response()->json([
            'is_duplicate' => $existingMediaFile !== null,
            'existing_file' => $existingMediaFile ? [
                'id' => $existingMediaFile->id,
                'mime_type' => $existingMediaFile->mime_type,
                'filesize' => $existingMediaFile->filesize,
            ] : null,
        ]);
    }
}
