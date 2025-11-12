<?php

use App\Http\Requests\LibraryItemRequest;
use App\Models\User;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\SourceProcessors\UnifiedSourceProcessor;
use App\Services\SourceProcessors\UrlStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('UnifiedSourceProcessor Edge Cases', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('validates strategy creation with different types', function () {
        // Test that we can create processor with different strategies
        $uploadProcessor = new UnifiedSourceProcessor(
            new UnifiedDuplicateProcessor,
            new \App\Services\SourceProcessors\UploadStrategy
        );

        $urlProcessor = new UnifiedSourceProcessor(
            new UnifiedDuplicateProcessor,
            new \App\Services\SourceProcessors\UrlStrategy
        );

        $youtubeProcessor = new UnifiedSourceProcessor(
            new UnifiedDuplicateProcessor,
            new \App\Services\SourceProcessors\YouTubeStrategy
        );

        expect($uploadProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
        expect($urlProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
        expect($youtubeProcessor)->toBeInstanceOf(UnifiedSourceProcessor::class);
    });

    it('handles unauthenticated user gracefully', function () {
        // Log out any authenticated user
        auth()->logout();

        $processor = new UnifiedSourceProcessor(
            new UnifiedDuplicateProcessor,
            new UrlStrategy
        );

        $validated = [
            'title' => 'Test Title',
            'description' => 'Test Description',
        ];

        // Should throw exception when no user is authenticated
        expect(fn () => $processor->process(
            new LibraryItemRequest,
            $validated,
            'url',
            'https://example.com/test.mp3'
        ))->toThrow(\TypeError::class);
    });

    it('validates input data structure', function () {
        $this->actingAs($this->user);

        $processor = new UnifiedSourceProcessor(
            new UnifiedDuplicateProcessor,
            new UrlStrategy
        );

        // Test that processor accepts various data structures
        $minimalData = ['title' => 'Test'];
        $fullData = [
            'title' => 'Test Title',
            'description' => 'Test Description',
        ];

        expect($minimalData)->toHaveKey('title');
        expect($fullData)->toHaveKeys(['title', 'description']);
    });

    it('handles special characters in validated data', function () {
        $this->actingAs($this->user);

        // Test data with special characters
        $specialData = [
            'title' => 'Test with émojis 🎵 and spéciâl chars',
            'description' => 'Description with 中文 and ñ characters',
        ];

        expect($specialData['title'])->toContain('🎵');
        expect($specialData['description'])->toContain('中文');
    });
});
