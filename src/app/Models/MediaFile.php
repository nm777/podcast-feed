<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_path',
        'file_hash',
        'mime_type',
        'filesize',
        'duration',
        'source_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function libraryItems()
    {
        return $this->hasMany(LibraryItem::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Find a media file by source URL.
     */
    public static function findBySourceUrl(string $sourceUrl): ?static
    {
        $query = static::where('source_url', $sourceUrl);

        return $query->first();
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
        return \App\Services\DuplicateDetectionService::findGlobalDuplicate($filePath);
    }

    /**
     * Check if a file is a duplicate for a specific user by calculating its hash.
     */
    public static function isDuplicateForUser(string $filePath, int $userId): ?static
    {
        return \App\Services\DuplicateDetectionService::findUserDuplicate($filePath, $userId);
    }
}
