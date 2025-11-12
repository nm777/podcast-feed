<?php

namespace App\Services\SourceProcessors;

interface SourceStrategyInterface
{
    /**
     * Validate the source based on strategy requirements.
     */
    public function validate(?string $sourceUrl): void;

    /**
     * Process a new source using strategy-specific logic.
     */
    public function processNewSource(\App\Models\LibraryItem $libraryItem, ?string $sourceUrl): void;

    /**
     * Get success message based on whether it was a duplicate.
     */
    public function getSuccessMessage(bool $isDuplicate): string;

    /**
     * Get processing message for new sources.
     */
    public function getProcessingMessage(): string;
}
