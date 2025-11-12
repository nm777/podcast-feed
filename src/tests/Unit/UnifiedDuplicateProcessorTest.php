<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
});

describe('UnifiedDuplicateProcessor', function () {
    beforeEach(function () {
        $this->processor = new UnifiedDuplicateProcessor;
        $this->user = User::factory()->create();
    });

    describe('processUrlDuplicate', function () {
        it('returns no duplicate when URL is new', function () {
            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/new-audio.mp3',
            ]);

            $result = $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/new-audio.mp3');

            expect($result)->toHaveKey('success');
            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file'])->toBeNull();
        });

        it('handles user duplicate for URL sources', function () {
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/existing-audio.mp3',
            ]);
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $mediaFile->id,
                'source_url' => 'https://example.com/existing-audio.mp3',
            ]);

            $newLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/existing-audio.mp3',
            ]);

            $result = $this->processor->processUrlDuplicate($newLibraryItem, 'https://example.com/existing-audio.mp3');

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeTrue();
            expect($result['media_file']->id)->toBe($mediaFile->id);
            expect($result['message'])->toContain('Duplicate file detected');

            // Verify library item was updated
            $newLibraryItem->refresh();
            expect($newLibraryItem->is_duplicate)->toBeTrue();
            expect($newLibraryItem->media_file_id)->toBe($mediaFile->id);
            expect($newLibraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);

            // Verify cleanup job was dispatched
            Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
        });

        it('handles global duplicate for URL sources', function () {
            $otherUser = User::factory()->create();
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $otherUser->id,
                'source_url' => 'https://example.com/shared-audio.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/shared-audio.mp3',
            ]);

            $result = $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/shared-audio.mp3');

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file']->id)->toBe($mediaFile->id);
            expect($result['message'])->toContain('Linked to existing media file');

            // Verify library item was updated
            $libraryItem->refresh();
            expect($libraryItem->is_duplicate)->toBeFalse();
            expect($libraryItem->media_file_id)->toBe($mediaFile->id);
            expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
        });

        it('handles user media file only scenario', function () {
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/media-file-only.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/media-file-only.mp3',
            ]);

            $result = $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/media-file-only.mp3');

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file']->id)->toBe($mediaFile->id);
            expect($result['message'])->toContain('Linked to existing media file');
        });
    });

    describe('processFileDuplicate', function () {
        it('returns no duplicate when file hash is new', function () {
            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $fileContent = 'unique file content';
            $filePath = 'temp-uploads/test-unique.mp3';
            Storage::disk('public')->put($filePath, $fileContent);

            $result = $this->processor->processFileDuplicate($libraryItem, $filePath);

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file'])->toBeNull();
        });

        it('handles user duplicate for file uploads', function () {
            $fileContent = 'duplicate file content';
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

            $newLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $filePath = 'temp-uploads/test-duplicate.mp3';
            Storage::disk('public')->put($filePath, $fileContent);

            $result = $this->processor->processFileDuplicate($newLibraryItem, $filePath);

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeTrue();
            expect($result['media_file']->id)->toBe($existingMediaFile->id);
            expect($result['message'])->toContain('Duplicate file detected');

            // Verify library item was updated
            $newLibraryItem->refresh();
            expect($newLibraryItem->is_duplicate)->toBeTrue();
            expect($newLibraryItem->media_file_id)->toBe($existingMediaFile->id);
            expect($newLibraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);

            // Verify cleanup job was dispatched
            Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
        });

        it('handles global duplicate for file uploads', function () {
            $otherUser = User::factory()->create();
            $fileContent = 'shared file content';
            $fileHash = hash('sha256', $fileContent);

            // Create existing media file for different user
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $otherUser->id,
                'file_hash' => $fileHash,
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $filePath = 'temp-uploads/test-shared.mp3';
            Storage::disk('public')->put($filePath, $fileContent);

            $result = $this->processor->processFileDuplicate($libraryItem, $filePath);

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file']->id)->toBe($existingMediaFile->id);
            expect($result['message'])->toContain('Linked to existing media file');

            // Verify library item was updated
            $libraryItem->refresh();
            expect($libraryItem->is_duplicate)->toBeFalse();
            expect($libraryItem->media_file_id)->toBe($existingMediaFile->id);
            expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
        });

        it('updates source URL when processing duplicate file upload with URL', function () {
            $fileContent = 'unique test file content for source URL update '.time();
            $fileHash = hash('sha256', $fileContent);

            // Create existing media file without source URL
            $existingMediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'file_hash' => $fileHash,
                'source_url' => null,
            ]);

            // Create existing library item that links to the media file
            $existingLibraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $existingMediaFile->id,
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/new-source.mp3',
            ]);

            $filePath = 'temp-uploads/test-with-url.mp3';
            Storage::disk('public')->put($filePath, $fileContent);

            $result = $this->processor->processFileDuplicate($libraryItem, $filePath);

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeTrue();

            // Verify source URL was updated on existing media file
            $existingMediaFile->refresh();
            expect($existingMediaFile->source_url)->toBe('https://example.com/new-source.mp3');
        });
    });

    describe('edge cases', function () {
        it('handles empty source URL gracefully', function () {
            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => null,
            ]);

            $result = $this->processor->processUrlDuplicate($libraryItem, '');

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file'])->toBeNull();
        });

        it('handles non-existent file gracefully', function () {
            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
            ]);

            $result = $this->processor->processFileDuplicate($libraryItem, 'non-existent-file.mp3');

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file'])->toBeNull();
        });

        it('excludes current library item from duplicate check', function () {
            $mediaFile = MediaFile::factory()->create([
                'user_id' => $this->user->id,
                'source_url' => 'https://example.com/self-reference.mp3',
            ]);

            $libraryItem = LibraryItem::factory()->create([
                'user_id' => $this->user->id,
                'media_file_id' => $mediaFile->id,
                'source_url' => 'https://example.com/self-reference.mp3',
            ]);

            // Process same library item - should not detect as duplicate but return existing media file
            $result = $this->processor->processUrlDuplicate($libraryItem, 'https://example.com/self-reference.mp3');

            expect($result['success'])->toBeTrue();
            expect($result['is_duplicate'])->toBeFalse();
            expect($result['media_file']->id)->toBe($mediaFile->id);
        });
    });
});
