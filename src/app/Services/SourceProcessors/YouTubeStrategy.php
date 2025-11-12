<?php

namespace App\Services\SourceProcessors;

use App\Jobs\ProcessYouTubeAudio;
use App\Services\YouTubeUrlValidator;

class YouTubeStrategy implements SourceStrategyInterface
{
    public function validate(?string $sourceUrl): void
    {
        if (! YouTubeUrlValidator::isValidYouTubeUrl($sourceUrl)) {
            throw new \InvalidArgumentException('Invalid YouTube URL provided.');
        }
    }

    public function processNewSource(\App\Models\LibraryItem $libraryItem, ?string $sourceUrl): void
    {
        // Process new YouTube URL
        ProcessYouTubeAudio::dispatch($libraryItem, $sourceUrl);
    }

    public function getSuccessMessage(bool $isDuplicate): string
    {
        if ($isDuplicate) {
            return 'This YouTube video has already been processed. The existing media file has been linked to this library item.';
        }

        return 'YouTube video added successfully. Processing...';
    }

    public function getProcessingMessage(): string
    {
        return 'YouTube video added successfully. Processing...';
    }
}
