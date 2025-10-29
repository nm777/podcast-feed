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
        if ($this->sourceUrl) {
            $contents = Http::get($this->sourceUrl)->body();
            $this->filePath = 'uploads/' . basename($this->sourceUrl);
            Storage::disk('local')->put($this->filePath, $contents);
        }

        $fileHash = hash_file('sha256', Storage::disk('local')->path($this->filePath));

        $mediaFile = MediaFile::firstOrCreate(
            ['file_hash' => $fileHash],
            [
                'file_path' => $this->filePath,
                'mime_type' => File::mimeType(Storage::disk('local')->path($this->filePath)),
                'filesize' => File::size(Storage::disk('local')->path($this->filePath)),
            ]
        );

        $this->libraryItem->media_file_id = $mediaFile->id;
        $this->libraryItem->save();
    }
}