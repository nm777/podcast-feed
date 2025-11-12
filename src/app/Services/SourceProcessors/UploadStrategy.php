<?php

namespace App\Services\SourceProcessors;

class UploadStrategy implements SourceStrategyInterface
{
    public function validate(?string $sourceUrl): void
    {
        // No validation needed for uploads
    }

    public function processNewSource(\App\Models\LibraryItem $libraryItem, ?string $sourceUrl): void
    {
        // Uploads are processed immediately in the main processor
        // No additional processing needed
    }

    public function getSuccessMessage(bool $isDuplicate): string
    {
        if ($isDuplicate) {
            return 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.';
        }

        return 'Media file uploaded successfully. Processing...';
    }

    public function getProcessingMessage(): string
    {
        return 'Media file uploaded successfully. Processing...';
    }
}
