<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path',
        'file_hash',
        'mime_type',
        'filesize',
        'duration',
        'source_url',
    ];

    public function libraryItems()
    {
        return $this->hasMany(LibraryItem::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return url(route('media.show', ['file_path' => $this->file_path]));
    }

    /**
     * Find a media file by source URL.
     */
    public static function findBySourceUrl(string $sourceUrl): ?static
    {
        return static::where('source_url', $sourceUrl)->first();
    }

    /**
     * Find a media file by file hash.
     */
    public static function findByHash(string $fileHash): ?static
    {
        return static::where('file_hash', $fileHash)->first();
    }

    /**
     * Check if a file is a duplicate by calculating its hash.
     */
    public static function isDuplicate(string $filePath): ?static
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $fileHash = hash_file('sha256', $filePath);

        return static::findByHash($fileHash);
    }
}
