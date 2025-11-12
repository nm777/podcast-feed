<?php

namespace App\Services\SourceProcessors;

use App\Services\YouTubeUrlValidator;
use Illuminate\Database\DatabaseManager;

class SourceProcessorFactory
{
    public static function create(string $sourceType): SourceProcessorInterface
    {
        $db = app(DatabaseManager::class);
        $duplicateDetection = app(\App\Services\DuplicateDetectionService::class);

        return match ($sourceType) {
            'upload' => new UploadSourceProcessor($db, $duplicateDetection),
            'url' => new UrlSourceProcessor($db, $duplicateDetection),
            'youtube' => new YouTubeSourceProcessor($db, $duplicateDetection),
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };
    }

    public static function validate(string $sourceType, ?string $sourceUrl): ?\Illuminate\Http\RedirectResponse
    {
        if ($sourceType === 'youtube' && ! YouTubeUrlValidator::isValidYouTubeUrl($sourceUrl)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['source_url' => 'Invalid YouTube URL']);
        }

        return null;
    }
}
