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
        $mediaFile = null;

        // Check if we already have this file from the same URL
        if ($this->sourceUrl) {
            $mediaFile = MediaFile::findBySourceUrl($this->sourceUrl);

            if ($mediaFile) {
                // File already exists from this URL, just link it
                $this->libraryItem->media_file_id = $mediaFile->id;
                $this->libraryItem->save();

                return;
            }
        }

        if ($this->sourceUrl) {
            try {
                $response = Http::timeout(60)->get($this->sourceUrl);

                if (! $response->successful()) {
                    $this->libraryItem->delete();

                    return;
                }

                $contents = $response->body();

                if (empty($contents)) {
                    $this->libraryItem->delete();

                    return;
                }

                $tempPath = 'temp-uploads/' . uniqid() . '_' . basename(parse_url($this->sourceUrl, PHP_URL_PATH) ?: 'download');
                Storage::disk('local')->put($tempPath, $contents);
            } catch (\Exception $e) {
                $this->libraryItem->delete();

                return;
            }
        } elseif ($this->filePath) {
            $tempPath = $this->filePath;
        }

        if (! $tempPath || ! Storage::disk('local')->exists($tempPath)) {
            $this->libraryItem->delete();

            return;
        }

        $fullPath = Storage::disk('local')->path($tempPath);
        $fileHash = hash_file('sha256', $fullPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $finalPath = 'media/' . $fileHash . '.' . $extension;

        // Check if file already exists with this hash (but different source)
        $mediaFile = MediaFile::where('file_hash', $fileHash)->first();

        if (! $mediaFile) {
            // Move file to final location using hash
            Storage::disk('local')->move($tempPath, $finalPath);

            $mediaFile = MediaFile::create([
                'file_path' => $finalPath,
                'file_hash' => $fileHash,
                'mime_type' => File::mimeType(Storage::disk('local')->path($finalPath)),
                'filesize' => File::size(Storage::disk('local')->path($finalPath)),
                'source_url' => $this->sourceUrl,
            ]);
        } else {
            // File already exists, clean up temp file
            Storage::disk('local')->delete($tempPath);

            // Update source URL if this is the first time we've seen it from a URL
            if ($this->sourceUrl && ! $mediaFile->source_url) {
                $mediaFile->source_url = $this->sourceUrl;
                $mediaFile->save();
            }
        }

        $this->libraryItem->media_file_id = $mediaFile->id;
        $this->libraryItem->save();
    }
}
