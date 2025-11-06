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
        fn($page) => $page->component('Library/Index')
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
        fn($page) => $page->component('Library/Index')
            ->has('libraryItems', 1)
            ->where('libraryItems.0.title', 'User Item')
    );
});

it('can upload a media file', function () {
    Storage::fake('local');
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
    Storage::fake('local');

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
    Storage::fake('local');

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/test-file.mp3',
    ]);

    Storage::disk('local')->put($mediaFile->file_path, 'fake content');

    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->actingAs($user)->delete("/library/{$libraryItem->id}");

    $this->assertDatabaseMissing('media_files', [
        'id' => $mediaFile->id,
    ]);

    Storage::disk('local')->assertMissing($mediaFile->file_path);
});

it('keeps media files when other users still reference them', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'file_path' => 'media/shared-file.mp3',
    ]);

    Storage::disk('local')->put($mediaFile->file_path, 'fake content');

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

    Storage::disk('local')->assertExists($mediaFile->file_path);
});

it('can add media file from URL', function () {
    Storage::fake('local');
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
    Storage::fake('local');

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
    Storage::fake('local');

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

    // Library item should be deleted on failure
    $this->assertDatabaseMissing('library_items', [
        'id' => $libraryItem->id,
    ]);
});

it('reuses existing media file when same URL is provided', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create([
        'source_url' => 'https://example.com/shared-audio.mp3',
    ]);

    // First user adds URL
    $response = $this->actingAs($user)->post('/library', [
        'title' => 'First Copy',
        'url' => 'https://example.com/shared-audio.mp3',
    ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'Duplicate URL detected. This file already exists in your library and will be removed automatically in 5 minutes.');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'First Copy',
        'source_url' => 'https://example.com/shared-audio.mp3',
        'media_file_id' => $mediaFile->id,
    ]);

    // Should schedule cleanup for duplicate
    Queue::assertPushed(\App\Jobs\CleanupDuplicateLibraryItem::class);
});

it('does not reuse files when URLs are different', function () {
    Storage::fake('local');
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
    $response->assertSessionHas('success', 'Media file URL added successfully. Downloading and processing...');

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
    Storage::fake('local');

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
    Storage::fake('local');
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
    $response2->assertSessionHas('success', 'Duplicate URL detected. This file already exists in your library and will be removed automatically in 5 minutes.');

    // No new job should be dispatched
    Queue::assertNotPushed(\App\Jobs\ProcessMediaFile::class);

    // Both users should have library items pointing to the same media file
    $this->assertDatabaseHas('library_items', [
        'user_id' => $user1->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user2->id,
        'media_file_id' => $mediaFile->id,
    ]);
});

it('detects duplicate file uploads by hash', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();

    // Create first media file with specific hash
    $mediaFile = MediaFile::factory()->create([
        'file_hash' => hash('sha256', 'test audio content'),
        'file_path' => 'media/existing-file.mp3',
    ]);

    // Create a fake file with the same content
    $file = UploadedFile::fake()->createWithContent('duplicate-audio.mp3', 'test audio content');

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
    Storage::fake('local');
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
    Storage::fake('local');

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
    Storage::fake('local');

    // Create a fake file
    $content = 'test audio content';
    $tempPath = 'temp/test-file.mp3';
    Storage::disk('local')->put($tempPath, $content);
    $fullPath = Storage::disk('local')->path($tempPath);

    // Create media file with same hash
    $mediaFile = MediaFile::factory()->create([
        'file_hash' => hash('sha256', $content),
    ]);

    // Test duplicate detection
    $duplicate = MediaFile::isDuplicate($fullPath);
    expect($duplicate)->not->toBeNull();
    expect($duplicate->id)->toBe($mediaFile->id);

    // Test with non-existent file
    $nonDuplicate = MediaFile::isDuplicate('/non/existent/file.mp3');
    expect($nonDuplicate)->toBeNull();

    // Clean up
    Storage::disk('local')->delete($tempPath);
});

it('marks duplicate library items and schedules cleanup', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();

    // Create existing media file
    $mediaFile = MediaFile::factory()->create([
        'file_hash' => hash('sha256', 'duplicate content'),
        'file_path' => 'media/existing-file.mp3',
    ]);

    // Create library item for duplicate upload
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'Duplicate Upload',
        'source_type' => 'upload',
    ]);

    // Create temp file with same content
    $tempPath = 'temp/duplicate-upload.mp3';
    Storage::disk('local')->put($tempPath, 'duplicate content');

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
