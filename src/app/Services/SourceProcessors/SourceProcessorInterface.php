<?php

namespace App\Services\SourceProcessors;

use App\Http\Requests\LibraryItemRequest;
use App\Models\LibraryItem;

interface SourceProcessorInterface
{
    /**
     * Process the source and create library item.
     *
     * @return array [LibraryItem $libraryItem, string $message]
     */
    public function process(LibraryItemRequest $request, array $validated, string $sourceType, ?string $sourceUrl): array;
}
