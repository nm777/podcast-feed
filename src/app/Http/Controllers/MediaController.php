<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(Request $request, string $file_path)
    {
        $mediaFile = MediaFile::where('file_path', $file_path)->firstOrFail();

        if (! Storage::disk('local')->exists($file_path)) {
            abort(404);
        }

        $file = Storage::disk('local')->get($file_path);
        $mimeType = $mediaFile->mime_type ?? 'application/octet-stream';
        $fileSize = $mediaFile->filesize ?? strlen($file);

        return Response::make($file, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
