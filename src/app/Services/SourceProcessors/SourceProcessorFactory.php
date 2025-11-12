<?php

namespace App\Services\SourceProcessors;

use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\YouTubeUrlValidator;

class SourceProcessorFactory
{
    public static function create(string $sourceType): UnifiedSourceProcessor
    {
        $duplicateProcessor = app(UnifiedDuplicateProcessor::class);
        $strategy = match ($sourceType) {
            'upload' => new UploadStrategy,
            'url' => new UrlStrategy,
            'youtube' => new YouTubeStrategy,
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };

        return new UnifiedSourceProcessor($duplicateProcessor, $strategy);
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
