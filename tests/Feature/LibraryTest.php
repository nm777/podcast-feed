<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
