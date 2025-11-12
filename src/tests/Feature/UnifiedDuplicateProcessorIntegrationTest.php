<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaProcessing\MediaProcessingService;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
});

describe('UnifiedDuplicateProcessor Integration', function () {
    beforeEach(function () {
        $this->processor = new UnifiedDuplicateProcessor;
        $this->mediaProcessingService = new MediaProcessingService(
            new \App\Services\MediaProcessing\MediaDownloader,
            new \App\Services\MediaProcessing\MediaValidator,
            new \App\Services\MediaProcessing\MediaStorageManager,
            $this->processor
        );
        $this->user = User::factory()->create();
    });

    describe('MediaProcessingService Integration', function () {
        it('processes URL with duplicate detection using unified processor', function () {
            // Create existing media file
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/existing-audio.mp3',
            ]);

            // Create existing library item that links to the media file
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $existingMediaFile->id,
                'source_url' => 'https://example.com/existing-audio.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/existing-audio.mp3',
            ]);

            $result = $this->mediaProcessingService->processFromUrl($libraryItem, 'https://example.com/existing-audio.mp3');

            expect($result['is_duplicate'])->toBeTrue();
            expect($result['media_file']->id)->toBe($existingMediaFile->id);
        });

        it('processes file upload with duplicate detection using unified processor', function () {
            $fileContent = 'test file content';
            $fileHash = hash('sha256', $fileContent);

            // Create existing media file
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'file_hash' => $fileHash,
            ]);

            // Create existing library item that links to media file
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $existingMediaFile->id,
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $filePath = 'temp-uploads/test-file.mp3';
            Storage::disk('public')->put($filePath, $fileContent);

            $result = $this->mediaProcessingService->processFromFile($libraryItem, $filePath);

            expect($result['is_duplicate'])->toBeTrue();
            expect($result['media_file']->id)->toBe($existingMediaFile->id);
        });

        it('processes new URL without duplicates using unified processor', function () {
            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/new-audio.mp3',
            ]);

            // Mock downloader to return fake content
            $fakeContent = 'fake audio content';
            $mockDownloader = $this->mock(\App\Services\MediaProcessing\MediaDownloader::class, function ($mock) use ($fakeContent) {
                $mock->shouldReceive('downloadFromUrl')
                    ->with('https://example.com/new-audio.mp3')
                    ->once()
                    ->andReturn($fakeContent);
            });

            // Create new service with mocked downloader
            $mockedService = new MediaProcessingService(
                $mockDownloader,
                new \App\Services\MediaProcessing\MediaValidator,
                new \App\Services\MediaProcessing\MediaStorageManager,
                $this->processor
            );

            $result = $mockedService->processFromUrl($libraryItem, 'https://example.com/new-audio.mp3');

            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file'])->not->toBeNull();
        });

        it('processes new file upload without duplicates using unified processor', function () {
            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $fileContent = 'unique file content';
            $filePath = 'temp-uploads/unique-file.mp3';
            Storage::disk('public')->put($filePath, $fileContent);

            $result = $this->mediaProcessingService->processFromFile($libraryItem, $filePath);

            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file'])->not->toBeNull();
        });
    });

    describe('Cross-User Duplicate Handling', function () {
        it('handles cross-user URL duplicates correctly', function () {
            $otherUser = User::factory()->create();

            // Create media file for different user
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $otherUser->id,
                'source_url' => 'https://example.com/shared-audio.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/shared-audio.mp3',
            ]);

            $result = $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/shared-audio.mp3');

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse(); // Cross-user is not marked as duplicate
            expect($result['media_file']->id)->toBe($existingMediaFile->id);
        });

        it('handles cross-user file duplicates correctly', function () {
            $otherUser = User::factory()->create();
            $fileContent = 'shared file content';
            $fileHash = hash('sha256', $fileContent);

            // Create media file for different user
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $otherUser->id,
                'file_hash' => $fileHash,
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $filePath = 'temp-uploads/shared-file.mp3';
            Storage::disk('public')->put($filePath, $fileContent);

            $result = $this->processor->processFileDuplicate($libraryItem, $filePath);

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse(); // Cross-user is not marked as duplicate
            expect($result['media_file']->id)->toBe($existingMediaFile->id);
        });
    });

    describe('Flash Messages', function () {
        it('sets correct flash message for user duplicates', function () {
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/duplicate-audio.mp3',
            ]);

            // Create existing library item that links to media file
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $mediaFile->id,
                'source_url' => 'https://example.com/duplicate-audio.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/duplicate-audio.mp3',
            ]);

            $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/duplicate-audio.mp3');

            expect(session()->has('warning'))->toBeTrue();
            expect(session('warning'))->toContain('Duplicate file detected');
        });

        it('sets correct flash message for global duplicates', function () {
            $otherUser = User::factory()->create();
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $otherUser->id,
                'source_url' => 'https://example.com/global-audio.mp3',
            ]);

            // Create existing library item for other user
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $otherUser->id,
                'media_file_id' => $mediaFile->id,
                'source_url' => 'https://example.com/global-audio.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/global-audio.mp3',
            ]);

            $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/global-audio.mp3');

            expect(session()->has('info'))->toBeTrue();
            expect(session('info'))->toContain('Linked to existing media file');
        });
    });

    describe('Cleanup Job Dispatch', function () {
        it('dispatches cleanup job for user duplicates', function () {
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/cleanup-test.mp3',
            ]);

            // Create existing library item that links to media file
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $mediaFile->id,
                'source_url' => 'https://example.com/cleanup-test.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/cleanup-test.mp3',
            ]);

            $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/cleanup-test.mp3');

            Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class, function ($job) use ($libraryItem) {
                return $job->libraryItem->id === $libraryItem->id;
            });
        });

        it('does not dispatch cleanup job for global duplicates', function () {
            $otherUser = User::factory()->create();
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $otherUser->id,
                'source_url' => 'https://example.com/no-cleanup.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/no-cleanup.mp3',
            ]);

            $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/no-cleanup.mp3');

            Queue::assertNotPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
        });
    });
});
