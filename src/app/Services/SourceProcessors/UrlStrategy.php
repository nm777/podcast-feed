<?php

namespace App\Services\SourceProcessors;

use App\Jobs\ProcessMediaFile;

class UrlStrategy implements SourceStrategyInterface
{
    public function validate(?string $sourceUrl): void
    {
        // Basic URL validation could be added here if needed
        if (empty($sourceUrl)) {
            throw new \InvalidArgumentException('URL is required for URL sources.');
        }
    }

    public function processNewSource(\App\Models\LibraryItem $libraryItem, ?string $sourceUrl): void
    {
        // Process new URL
        ProcessMediaFile::dispatch($libraryItem, $sourceUrl);
    }

    public function getSuccessMessage(bool $isDuplicate): string
    {
        if ($isDuplicate) {
            return 'This URL has already been processed. The existing media file has been linked to this library item.';
        }

        return 'URL added successfully. Processing...';
    }

    public function getProcessingMessage(): string
    {
        return 'URL added successfully. Processing...';
    }
}
