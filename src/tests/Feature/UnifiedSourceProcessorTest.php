<?php

use App\Http\Requests\LibraryItemRequest;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\SourceProcessors\UnifiedSourceProcessor;
use App\Services\SourceProcessors\UploadStrategy;
use App\Services\SourceProcessors\UrlStrategy;
use App\Services\SourceProcessors\YouTubeStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

describe('UnifiedSourceProcessor', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->duplicateProcessor = new UnifiedDuplicateProcessor;
        $this->actingAs($this->user);
    });

    describe('Upload Processing', function () {
        beforeEach(function () {
            $this->strategy = new UploadStrategy;
            $this->processor = new UnifiedSourceProcessor($this->duplicateProcessor, $this->strategy);
        });

        it('processes new file upload without duplicates', function () {
            $fileContent = 'unique test file content';
            $fileHash = hash('sha256', $fileContent);

            $file = UploadedFile::fake()->create('test.mp3', 1000, 'audio/mpeg');
            $request = new LibraryItemRequest;
            $request->files->set('file', $file);

            $validated = [
                'title' => 'Test Upload',
                'description' => 'Test Description',
            ];

            $result = $this->processor->process($request, $validated, 'upload', null);

            expect($result)->toHaveCount(2);
            [$libraryItem, $message] = $result;

            expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
            expect($libraryItem->title)->toBe('Test Upload');
            expect($libraryItem->source_type)->toBe('upload');
            expect($libraryItem->user_id)->toBe($this->user->id);
            expect($message)->toBe('Media file uploaded successfully. Processing...');
        });

        it('processes duplicate file upload correctly', function () {
            $fileContent = 'duplicate test file content';
            $fileHash = hash('sha256', $fileContent);

            // Create existing media file
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'file_hash' => $fileHash,
            ]);

            // Create existing library item
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $existingMediaFile->id,
            ]);

            // Create fake file with specific content that matches the hash
            $tempPath = sys_get_temp_dir().'/test_duplicate.mp3';
            file_put_contents($tempPath, $fileContent);
            $file = new UploadedFile($tempPath, 'test.mp3', 'audio/mpeg', null, true);

            $request = new LibraryItemRequest;
            $request->files->set('file', $file);

            $validated = [
                'title' => 'Duplicate Upload',
                'description' => 'Duplicate Description',
            ];

            $result = $this->processor->process($request, $validated, 'upload', null);

            expect($result)->toHaveCount(2);
            [$libraryItem, $message] = $result;

            expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
            expect($libraryItem->title)->toBe('Duplicate Upload');
            expect($libraryItem->media_file_id)->toBe($existingMediaFile->id);
            expect($libraryItem->is_duplicate)->toBeTrue();
            expect($message)->toContain('Duplicate file detected');
        });
    });

    describe('URL Processing', function () {
        beforeEach(function () {
            $this->strategy = new UrlStrategy;
            $this->processor = new UnifiedSourceProcessor($this->duplicateProcessor, $this->strategy);
        });

        it('processes new URL without duplicates', function () {
            $validated = [
                'title' => 'Test URL',
                'description' => 'Test URL Description',
            ];

            $result = $this->processor->process(
                new LibraryItemRequest,
                $validated,
                'url',
                'https://example.com/new-audio.mp3'
            );

            expect($result)->toHaveCount(2);
            [$libraryItem, $message] = $result;

            expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
            expect($libraryItem->title)->toBe('Test URL');
            expect($libraryItem->source_type)->toBe('url');
            expect($libraryItem->source_url)->toBe('https://example.com/new-audio.mp3');
            expect($message)->toBe('URL added successfully. Processing...');
        });

        it('processes duplicate URL correctly', function () {
            // Create existing media file
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/duplicate-audio.mp3',
            ]);

            // Create existing library item
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $existingMediaFile->id,
                'source_url' => 'https://example.com/duplicate-audio.mp3',
            ]);

            $validated = [
                'title' => 'Duplicate URL',
                'description' => 'Duplicate URL Description',
            ];

            $result = $this->processor->process(
                new LibraryItemRequest,
                $validated,
                'url',
                'https://example.com/duplicate-audio.mp3'
            );

            expect($result)->toHaveCount(2);
            [$libraryItem, $message] = $result;

            expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
            expect($libraryItem->title)->toBe('Duplicate URL');
            expect($libraryItem->media_file_id)->toBe($existingMediaFile->id);
            expect($libraryItem->is_duplicate)->toBeTrue();
            expect($message)->toContain('already been processed');
        });
    });

    describe('YouTube Processing', function () {
        beforeEach(function () {
            $this->strategy = new YouTubeStrategy;
            $this->processor = new UnifiedSourceProcessor($this->duplicateProcessor, $this->strategy);
        });

        it('validates YouTube URL correctly', function () {
            $validated = [
                'title' => 'Test YouTube',
                'description' => 'Test YouTube Description',
            ];

            expect(fn () => $this->processor->process(
                new LibraryItemRequest,
                $validated,
                'youtube',
                'invalid-url'
            ))->toThrow(\InvalidArgumentException::class, 'Invalid YouTube URL provided.');
        });

        it('processes new YouTube URL without duplicates', function () {
            $validated = [
                'title' => 'Test YouTube',
                'description' => 'Test YouTube Description',
            ];

            $result = $this->processor->process(
                new LibraryItemRequest,
                $validated,
                'youtube',
                'https://www.youtube.com/watch?v=test123'
            );

            expect($result)->toHaveCount(2);
            [$libraryItem, $message] = $result;

            expect($libraryItem)->toBeInstanceOf(LibraryItem::class);
            expect($libraryItem->title)->toBe('Test YouTube');
            expect($libraryItem->source_type)->toBe('youtube');
            expect($libraryItem->source_url)->toBe('https://www.youtube.com/watch?v=test123');
            expect($message)->toBe('YouTube video added successfully. Processing...');
        });
    });

    describe('Strategy Messages', function () {
        it('returns correct messages for upload strategy', function () {
            $strategy = new UploadStrategy;

            expect($strategy->getSuccessMessage(true))->toContain('Duplicate file detected');
            expect($strategy->getSuccessMessage(false))->toBe('Media file uploaded successfully. Processing...');
            expect($strategy->getProcessingMessage())->toBe('Media file uploaded successfully. Processing...');
        });

        it('returns correct messages for URL strategy', function () {
            $strategy = new UrlStrategy;

            expect($strategy->getSuccessMessage(true))->toContain('already been processed');
            expect($strategy->getSuccessMessage(false))->toBe('URL added successfully. Processing...');
            expect($strategy->getProcessingMessage())->toBe('URL added successfully. Processing...');
        });

        it('returns correct messages for YouTube strategy', function () {
            $strategy = new YouTubeStrategy;

            expect($strategy->getSuccessMessage(true))->toContain('YouTube video has already been processed');
            expect($strategy->getSuccessMessage(false))->toBe('YouTube video added successfully. Processing...');
            expect($strategy->getProcessingMessage())->toBe('YouTube video added successfully. Processing...');
        });
    });
});
