<?php

use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;

it('allows feed owner to view edit page', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/feeds/{$feed->id}/edit");

    $response->assertOk()
        ->assertInertia(
            fn($page) => $page->component('feeds/edit')
                ->has('feed')
                ->has('userLibraryItems')
        );
});

it('prevents non-owners from viewing edit page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->get("/feeds/{$feed->id}/edit");

    $response->assertForbidden();
});

it('allows feed owner to update feed details', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->put("/feeds/{$feed->id}", [
        'title' => 'Updated Feed Title',
        'description' => 'Updated description',
        'is_public' => true,
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertDatabaseHas('feeds', [
        'id' => $feed->id,
        'title' => 'Updated Feed Title',
        'description' => 'Updated description',
        'is_public' => true,
    ]);
});

it('prevents non-owners from updating feed', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->put("/feeds/{$feed->id}", [
        'title' => 'Hacked Title',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('feeds', [
        'id' => $feed->id,
        'title' => 'Hacked Title',
    ]);
});

it('validates feed update request', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->put("/feeds/{$feed->id}", [
        'title' => '',
        'description' => str_repeat('a', 1001),
    ]);

    $response->assertSessionHasErrors(['title', 'description']);
});

it('allows adding items to feed', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->actingAs($user)->put("/feeds/{$feed->id}", [
        'title' => $feed->title,
        'description' => $feed->description,
        'is_public' => $feed->is_public,
        'items' => [
            ['library_item_id' => $libraryItem->id, 'sequence' => 0],
        ],
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertDatabaseHas('feed_items', [
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
        'sequence' => 0,
    ]);
});

it('allows removing items from feed', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);
    $feedItem = FeedItem::factory()->create([
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem->id,
        'sequence' => 0,
    ]);

    $response = $this->actingAs($user)->put("/feeds/{$feed->id}", [
        'title' => $feed->title,
        'description' => $feed->description,
        'is_public' => $feed->is_public,
        'items' => [],
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertDatabaseMissing('feed_items', [
        'id' => $feedItem->id,
    ]);
});

it('allows reordering items in feed', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $mediaFile1 = MediaFile::factory()->create();
    $mediaFile2 = MediaFile::factory()->create();

    $libraryItem1 = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile1->id,
    ]);
    $libraryItem2 = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile2->id,
    ]);

    $response = $this->actingAs($user)->put("/feeds/{$feed->id}", [
        'title' => $feed->title,
        'description' => $feed->description,
        'is_public' => $feed->is_public,
        'items' => [
            ['library_item_id' => $libraryItem2->id, 'sequence' => 0],
            ['library_item_id' => $libraryItem1->id, 'sequence' => 1],
        ],
    ]);

    $response->assertRedirect('/dashboard');

    $this->assertDatabaseHas('feed_items', [
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem2->id,
        'sequence' => 0,
    ]);

    $this->assertDatabaseHas('feed_items', [
        'feed_id' => $feed->id,
        'library_item_id' => $libraryItem1->id,
        'sequence' => 1,
    ]);
});

it('validates library item exists when adding to feed', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->put("/feeds/{$feed->id}", [
        'title' => $feed->title,
        'description' => $feed->description,
        'is_public' => $feed->is_public,
        'items' => [
            ['library_item_id' => 999, 'sequence' => 0],
        ],
    ]);

    $response->assertSessionHasErrors(['items.0.library_item_id']);
});

it('shows user library items on edit page', function () {
    $user = User::factory()->create();
    $feed = Feed::factory()->create(['user_id' => $user->id]);

    $mediaFile = MediaFile::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->actingAs($user)->get("/feeds/{$feed->id}/edit");

    $response->assertOk()
        ->assertInertia(
            fn($page) => $page->component('feeds/edit')
                ->has('userLibraryItems', 1)
                ->where('userLibraryItems.0.id', $libraryItem->id)
        );
});
