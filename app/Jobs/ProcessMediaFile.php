<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use App\Models\MediaFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessMediaFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $libraryItem;

    protected $sourceUrl;

    protected $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(LibraryItem $libraryItem, $sourceUrl = null, $filePath = null)
    {
        $this->libraryItem = $libraryItem;
        $this->sourceUrl = $sourceUrl;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tempPath = null;

        if ($this->sourceUrl) {
            $contents = Http::get($this->sourceUrl)->body();
            $tempPath = 'temp-uploads/' . uniqid() . '_' . basename($this->sourceUrl);
            Storage::disk('local')->put($tempPath, $contents);
        } elseif ($this->filePath) {
            $tempPath = $this->filePath;
        }

        if (! $tempPath) {
            return;
        }

        $fullPath = Storage::disk('local')->path($tempPath);
        $fileHash = hash_file('sha256', $fullPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $finalPath = 'media/' . $fileHash . '.' . $extension;

        // Check if file already exists with this hash
        $mediaFile = MediaFile::where('file_hash', $fileHash)->first();

        if (! $mediaFile) {
            // Move file to final location using hash
            Storage::disk('local')->move($tempPath, $finalPath);

            $mediaFile = MediaFile::create([
                'file_path' => $finalPath,
                'file_hash' => $fileHash,
                'mime_type' => File::mimeType(Storage::disk('local')->path($finalPath)),
                'filesize' => File::size(Storage::disk('local')->path($finalPath)),
            ]);
        } else {
            // File already exists, clean up temp file
            Storage::disk('local')->delete($tempPath);
        }

        $this->libraryItem->media_file_id = $mediaFile->id;
        $this->libraryItem->save();
    }
}
