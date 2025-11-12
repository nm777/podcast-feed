<?php

namespace App\Services;

use App\Models\LibraryItem;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;

class DuplicateDetectionService
{
    /**
     * Calculate file hash from storage path or file system.
     */
    public static function calculateFileHash(string $filePath): ?string
    {
        // Try to get file content from storage first
        try {
            $content = Storage::disk('public')->get($filePath);
            if ($content === false) {
                // Fallback to real file system if storage doesn't have it
                if (! file_exists($filePath)) {
                    return null;
                }

                return hash_file('sha256', $filePath);
            } else {
                return hash('sha256', $content);
            }
        } catch (\Exception $e) {
            // Fallback to real file system
            if (! file_exists($filePath)) {
                return null;
            }

            return hash_file('sha256', $filePath);
        }
    }

    /**
     * Check if a file is a duplicate globally by calculating its hash.
     */
    public static function findGlobalDuplicate(string $filePath): ?MediaFile
    {
        $fileHash = self::calculateFileHash($filePath);

        if (! $fileHash) {
            return null;
        }

        return MediaFile::findByHash($fileHash);
    }

    /**
     * Check if a file is a duplicate for a specific user by calculating its hash.
     */
    public static function findUserDuplicate(string $filePath, int $userId): ?MediaFile
    {
        $fileHash = self::calculateFileHash($filePath);

        if (! $fileHash) {
            return null;
        }

        return LibraryItem::findByHashForUser($fileHash, $userId)?->mediaFile;
    }

    /**
     * Check if a URL already exists for a specific user.
     */
    public static function findUrlDuplicateForUser(string $sourceUrl, int $userId): ?LibraryItem
    {
        if (! $sourceUrl) {
            return null;
        }

        return LibraryItem::findBySourceUrlForUser($sourceUrl, $userId);
    }

    /**
     * Check if a URL exists globally (any user).
     */
    public static function findGlobalUrlDuplicate(string $sourceUrl): ?MediaFile
    {
        if (! $sourceUrl) {
            return null;
        }

        return MediaFile::findBySourceUrl($sourceUrl);
    }

    /**
     * Comprehensive duplicate detection for file uploads.
     * Returns array with duplicate information and appropriate actions.
     */
    public static function analyzeFileUpload(string $filePath, int $userId): array
    {
        $userDuplicate = self::findUserDuplicate($filePath, $userId);
        $globalDuplicate = self::findGlobalDuplicate($filePath);

        return [
            'is_user_duplicate' => (bool) $userDuplicate,
            'is_global_duplicate' => (bool) $globalDuplicate,
            'user_duplicate_media_file' => $userDuplicate,
            'global_duplicate_media_file' => $globalDuplicate,
            'file_hash' => self::calculateFileHash($filePath),
            'should_link_to_user_duplicate' => (bool) $userDuplicate,
            'should_link_to_global_duplicate' => ! $userDuplicate && (bool) $globalDuplicate,
            'should_create_new_file' => ! $userDuplicate && ! $globalDuplicate,
        ];
    }

    /**
     * Comprehensive duplicate detection for URL sources.
     * Returns array with duplicate information and appropriate actions.
     */
    public static function analyzeUrlSource(string $sourceUrl, int $userId, ?int $excludeLibraryItemId = null): array
    {
        $userDuplicate = self::findUrlDuplicateForUser($sourceUrl, $userId);
        $globalDuplicate = self::findGlobalUrlDuplicate($sourceUrl);

        // Exclude current library item from duplicate check
        if ($excludeLibraryItemId && $userDuplicate && $userDuplicate->id === $excludeLibraryItemId) {
            $userDuplicate = null;
        }

        // Check if user has a MediaFile with this URL but no LibraryItem (edge case)
        $userMediaFileOnly = false;
        if (! $userDuplicate && $globalDuplicate && $globalDuplicate->user_id === $userId) {
            $userMediaFileOnly = true;
        }

        return [
            'is_user_duplicate' => (bool) $userDuplicate || $userMediaFileOnly,
            'is_global_duplicate' => (bool) $globalDuplicate,
            'user_duplicate_library_item' => $userDuplicate,
            'global_duplicate_media_file' => $globalDuplicate,
            'user_media_file_only' => $userMediaFileOnly,
            'should_link_to_user_duplicate' => (bool) $userDuplicate,
            'should_link_to_user_media_file' => $userMediaFileOnly,
            'should_link_to_global_duplicate' => ! $userDuplicate && ! $userMediaFileOnly && $globalDuplicate && $globalDuplicate->user_id !== $userId,
            'should_create_new_file' => ! $userDuplicate && ! $userMediaFileOnly && ! $globalDuplicate,
        ];
    }
}
