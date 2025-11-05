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
    $response->assertSessionHas('success', 'Media file already exists. Added to your library.');

    $this->assertDatabaseHas('library_items', [
        'user_id' => $user->id,
        'title' => 'First Copy',
        'source_url' => 'https://example.com/shared-audio.mp3',
        'media_file_id' => $mediaFile->id,
    ]);

    // No job should be dispatched since file already exists
    Queue::assertNotPushed(\App\Jobs\ProcessMediaFile::class);
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
    $response2->assertSessionHas('success', 'Media file already exists. Added to your library.');

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
