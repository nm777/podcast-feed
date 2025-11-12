<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('displays library page for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/library');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->component('Library/Index')
    );
});

it('shows only authenticated user library items', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $mediaFile = MediaFile::factory()->create();

    $userItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'User Item',
    ]);

    $otherUserItem = LibraryItem::factory()->create([
        'user_id' => $otherUser->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Other User Item',
    ]);

    $response = $this->actingAs($user)->get('/library');

    $response->assertInertia(
        fn ($page) => $page->component('Library/Index')
            ->has('libraryItems', 1)
            ->where('libraryItems.0.title', 'User Item')
    );
});

it('can upload a media file', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('test-audio.mp3', 1000, 'audio/mpeg');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'description' => 'Test Description',
        'file' => $file,
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Test Audio',
        'description' => 'Test Description',
        'source_type' => 'upload',
    ]);

    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('validates file upload requirements', function () {
    $user = User::factory()->create();

    // Test missing file
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
    ]);

    $response->assertSessionHasErrors('file');

    // Test invalid file type
    $file = UploadedFile::fake()->create('test.txt', 1000, 'text/plain');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
});

it('can delete a library item', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->actingAs($user)->delete("/library/{$libraryItem->id}");

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('library_items', [
        'id' => $libraryItem->id,
    ]);
});

it('cannot delete another user library item', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $otherUser->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->actingAs($user)->delete("/library/{$libraryItem->id}");

    $response->assertForbidden();
});

it('deletes orphaned media files when last library item is removed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/test-file.mp3',
    ]);

    Storage::disk('public')->put($mediaFile->file_path, 'fake content');

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->actingAs($user)->delete("/library/{$libraryItem->id}");

    $this->assertDatabaseMissing('media_files', [
        'id' => $mediaFile->id,
    ]);

    Storage::disk('public')->assertMissing($mediaFile->file_path);
});

it('keeps media files when other users still reference them', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/shared-file.mp3',
    ]);

    Storage::disk('public')->put($mediaFile->file_path, 'fake content');

    $userItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $otherUserItem = LibraryItem::factory()->create([
        'user_id' => $otherUser->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->actingAs($user)->delete("/library/{$userItem->id}");

    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
    ]);

    Storage::disk('public')->assertExists($mediaFile->file_path);
});

it('can add media file from URL', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test URL Audio',
        'description' => 'Test Description from URL',
        'url' => 'https://example.com/test-audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Test URL Audio',
        'description' => 'Test Description from URL',
        'source_type' => 'url',
        'source_url' => 'https://example.com/test-audio.mp3',
    ]);

    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('validates URL requirements', function () {
    $user = User::factory()->create();

    // Test missing both file and URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
    ]);

    $response->assertSessionHasErrors(['file', 'url']);

    // Test invalid URL format
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'url' => 'not-a-valid-url',
    ]);

    $response->assertSessionHasErrors('url');

    // Test URL without supported file extension
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Test Audio',
        'url' => 'https://example.com/test.txt',
    ]);

    $response->assertSessionHasErrors('url');
});

it('processes media file from URL correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/test-audio.mp3',
    ]);

    // Mock HTTP response
    Http::fake([
        'https://example.com/test-audio.mp3' => Http::response('fake audio content', 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/test-audio.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile)->not->toBeNull();
    expect($mediaFile->file_hash)->toBe(hash('sha256', 'fake audio content'));
    // MIME type is detected from file extension by the system
    expect($mediaFile->mime_type)->toBe('text/plain'); // Storage::fake() returns text/plain for all files
});

it('handles URL download failures gracefully', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/not-found.mp3',
    ]);

    // Mock failed HTTP response
    Http::fake([
        'https://example.com/not-found.mp3' => Http::response('Not Found', 404),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/not-found.mp3', null);
    $job->handle();

    // Library item should be marked as failed
    $this->assertDatabaseHas('library_items', [
        'id' => $libraryItem->id,
        'processing_status' => \App\ProcessingStatusType::FAILED->value,
    ]);
});

it('handles JavaScript redirect pages correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3',
    ]);

    $htmlRedirectPage = '<!DOCTYPE html><html><head><title>Redirect</title></head><body><script>window.location.replace("https://file-examples.com/storage/fef7fa310369115b497def4/file_example_MP3_700KB.mp3");</script></body></html>';
    $mp3Content = 'ID3fake audio content';

    // Mock the redirect page and the final MP3 file
    Http::fake([
        'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3' => Http::response($htmlRedirectPage, 200),
        'https://file-examples.com/storage/fef7fa310369115b497def4/file_example_MP3_700KB.mp3' => Http::response($mp3Content, 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile->file_hash)->toBe(hash('sha256', $mp3Content));
});

it('fails when JavaScript redirect cannot be resolved', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/redirect.mp3',
    ]);

    $htmlRedirectPage = '<!DOCTYPE html><html><head><title>Redirect</title></head><body><script>window.location.replace("https://example.com/final.mp3");</script></body></html>';

    // Mock of redirect page but fail to final request
    Http::fake([
        'https://example.com/redirect.mp3' => Http::response($htmlRedirectPage, 200),
        'https://example.com/final.mp3' => Http::response('Not Found', 404),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/redirect.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::FAILED);
    expect($libraryItem->processing_error)->toContain('Got HTML redirect page instead of media file');
});

it('handles file-examples.com JavaScript redirect pattern correctly', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3',
    ]);

    // Simulate the exact HTML redirect pattern from file-examples.com
    $htmlRedirectPage = '<!DOCTYPE html><html><head><title>File Examples | Download redirect...</title></head><body><script>document.addEventListener(\'DOMContentLoaded\', function(){setTimeout(function (){url=window.location.href.replace(\'file-examples.com/wp-content/storage/\',\'file-examples.com/storage/fef7fa310369115b497def4/\'); window.location.replace(url);}, 3000);}, false);</script></body></html>';

    $mp3Content = 'ID3'.str_repeat('x', 100); // Valid MP3 content with ID3 tag

    // Mock the redirect page and final MP3 file
    Http::fake([
        'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3' => Http::response($htmlRedirectPage, 200),
        'https://file-examples.com/storage/fef7fa310369115b497def4/2017/11/file_example_MP3_700KB.mp3' => Http::response($mp3Content, 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://file-examples.com/wp-content/storage/2017/11/file_example_MP3_700KB.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->processing_status)->toBe(\App\ProcessingStatusType::COMPLETED);
    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile->file_hash)->toBe(hash('sha256', $mp3Content));
    expect($mediaFile->filesize)->toBe(strlen($mp3Content));
    // Storage::fake() returns text/plain for all files, so we check that it's not HTML
    expect($mediaFile->mime_type)->not->toBe('text/html');
});

it('reuses existing media file when same URL is provided', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'source_url' => 'https://example.com/shared-audio.mp3',
    ]);
    $existingLibraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/shared-audio.mp3',
    ]);

    // First user adds URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'First Copy',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'This URL has already been processed. The existing media file has been linked to this library item.');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'First Copy',
        'source_url' => 'https://example.com/shared-audio.mp3',
        'media_file_id' => $mediaFile->id,
    ]);

    // Should not schedule cleanup - duplicates are now linked immediately
    Queue::assertNotPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
});

it('does not reuse files when URLs are different', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'source_url' => 'https://example.com/different-audio.mp3',
    ]);

    // User adds a different URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Different Audio',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'URL added successfully. Processing...');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Different Audio',
        'source_url' => 'https://example.com/shared-audio.mp3',
        'media_file_id' => null, // Will be set by job
    ]);

    // Job should be dispatched since URL is different
    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('stores source URL when downloading new file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'url',
        'source_url' => 'https://example.com/new-audio.mp3',
    ]);

    // Mock HTTP response
    Http::fake([
        'https://example.com/new-audio.mp3' => Http::response('new audio content', 200, [
            'Content-Type' => 'audio/mpeg',
        ]),
    ]);

    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/new-audio.mp3', null);
    $job->handle();

    $libraryItem->refresh();

    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = $libraryItem->mediaFile;
    expect($mediaFile)->not->toBeNull();
    expect($mediaFile->source_url)->toBe('https://example.com/new-audio.mp3');
});

it('multiple users can reuse same file from same URL', function () {
    Storage::fake('public');
    Queue::fake();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // First user downloads the file
    Http::fake([
        'https://example.com/shared-audio.mp3' => Http::response('shared audio content', 200),
    ]);

    $response1 = $this->actingAs($user1)->post('/library', [
        'title' => 'User 1 Copy',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response1->assertRedirect('/library');
    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);

    // Process the job manually to create the media file
    $libraryItem = LibraryItem::where('title', 'User 1 Copy')->first();
    $job = new \App\Jobs\ProcessMediaFile($libraryItem, 'https://example.com/shared-audio.mp3', null);
    $job->handle();

    $mediaFile = MediaFile::where('source_url', 'https://example.com/shared-audio.mp3')->first();
    expect($mediaFile)->not->toBeNull();

    // Second user adds the same URL
    Queue::fake(); // Reset queue fake
    $response2 = $this->actingAs($user2)->post('/library', [
        'title' => 'User 2 Copy',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response2->assertRedirect('/library');
    $response2->assertSessionHas('success', 'This URL has already been processed. The existing media file has been linked to this library item.');

    // No new job should be dispatched since we reuse the existing media file
    Queue::assertNotPushed(\App\Jobs\ProcessMediaFile::class);

    // User1 should have library item pointing to their media file
    $this->assertDatabaseHas('library_items', [
        'user_id' => $user1->id,
        'media_file_id' => $mediaFile->id,
    ]);

    // User2 should have their own library item with their own media file (cross-user deduplication)
    $user2LibraryItem = \App\Models\LibraryItem::where('user_id', $user2->id)
        ->where('title', 'User 2 Copy')
        ->first();
    expect($user2LibraryItem)->not->toBeNull();
    expect($user2LibraryItem->media_file_id)->toBe($mediaFile->id); // Should be same - we reuse media files
    expect($user2LibraryItem->is_duplicate)->toBeTrue(); // Cross-user links are now marked as duplicates
});

it('detects duplicate file uploads by hash', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create first media file with specific hash
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_hash' => hash('sha256', 'test audio content'),
        'file_path' => 'media/existing-file.mp3',
    ]);

    // Create a library item that references this media file
    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Original File',
        'source_type' => 'upload',
        'processing_status' => \App\ProcessingStatusType::COMPLETED,
    ]);

    // Create actual file in storage so duplicate detection works
    Storage::disk('public')->put($mediaFile->file_path, 'test audio content');

    // Create a fake file with the same content and manually store it
    $file = UploadedFile::fake()->createWithContent('duplicate-audio.mp3', 'test audio content');
    $tempPath = $file->store('temp-uploads');

    // Manually put the file content in storage since UploadedFile::fake() doesn't work with store()
    Storage::disk('public')->put($tempPath, 'test audio content');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'Duplicate File',
        'description' => 'This should be detected as duplicate',
        'file' => $file,
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.');

    // Should create library item linked to existing media file and marked as duplicate
    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'Duplicate File',
        'source_type' => 'upload',
        'media_file_id' => $mediaFile->id,
        'is_duplicate' => true,
    ]);

    // Should schedule cleanup for duplicate
    Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
});

it('processes non-duplicate file uploads normally', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create existing media file with different hash
    MediaFile::factory()->create([
        'file_hash' => hash('sha256', 'different content'),
        'file_path' => 'media/different-file.mp3',
    ]);

    // Create a fake file with different content
    $file = UploadedFile::fake()->createWithContent('new-audio.mp3', 'new unique content');

    $response = $this->actingAs($user)->post('/library', [
        'title' => 'New File',
        'description' => 'This should be processed normally',
        'file' => $file,
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'Media file uploaded successfully. Processing...');

    // Should create library item without media_file_id initially
    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'New File',
        'media_file_id' => null,
        'source_type' => 'upload',
    ]);

    // Job should be dispatched for new files
    Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);
});

it('MediaFile model can find duplicates by hash', function () {
    Storage::fake('public');

    // Create a media file with known hash
    $knownHash = hash('sha256', 'test content');
    $mediaFile = MediaFile::factory()->create([
        'file_hash' => $knownHash,
    ]);

    // Test findByHash method
    $found = MediaFile::findByHash($knownHash);
    expect($found)->not->toBeNull();
    expect($found->id)->toBe($mediaFile->id);

    // Test with non-existent hash
    $notFound = MediaFile::findByHash('nonexistenthash');
    expect($notFound)->toBeNull();
});

it('MediaFile model can check file duplicates', function () {
    Storage::fake('public');

    // Create a fake file
    $content = 'test audio content';
    $tempPath = 'temp/test-file.mp3';
    Storage::disk('public')->put($tempPath, $content);
    $fullPath = Storage::disk('public')->path($tempPath);

    // Create media file with same hash
    $mediaFile = MediaFile::factory()->create([
        'file_hash' => hash('sha256', $content),
    ]);

    // Test duplicate detection
    $duplicate = MediaFile::isDuplicate($tempPath);
    expect($duplicate)->not->toBeNull();
    expect($duplicate->id)->toBe($mediaFile->id);

    // Test with non-existent file
    $nonDuplicate = MediaFile::isDuplicate('/non/existent/file.mp3');
    expect($nonDuplicate)->toBeNull();

    // Clean up
    Storage::disk('public')->delete($tempPath);
});

it('marks duplicate library items and schedules cleanup', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create existing media file
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_hash' => hash('sha256', 'duplicate content'),
        'file_path' => 'media/existing-file.mp3',
    ]);

    // Create existing library item that references the media file
    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'title' => 'Original File',
        'source_type' => 'upload',
        'processing_status' => \App\ProcessingStatusType::COMPLETED,
    ]);

    // Create library item for duplicate upload
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'Duplicate Upload',
        'source_type' => 'upload',
    ]);

    // Create temp file with same content
    $tempPath = 'temp/duplicate-upload.mp3';
    Storage::disk('public')->put($tempPath, 'duplicate content');

    // Process the file
    $job = new \App\Jobs\ProcessMediaFile($libraryItem, null, $tempPath);
    $job->handle();

    $libraryItem->refresh();

    // Should be marked as duplicate
    expect($libraryItem->is_duplicate)->toBeTrue();
    expect($libraryItem->duplicate_detected_at)->not->toBeNull();
    expect($libraryItem->media_file_id)->toBe($mediaFile->id);

    // Should schedule cleanup job
    Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class, function ($job) use ($libraryItem) {
        return $job->libraryItem->id === $libraryItem->id;
    });
});
