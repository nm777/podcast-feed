<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RssController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/rss/{user_guid}/{feed_slug}', [RssController::class, 'show'])->name('rss.show');

Route::get('/media/{file_path}', [MediaController::class, 'show'])->name('media.show')->where('file_path', '.*');

Route::post('check-url-duplicate', [App\Http\Controllers\Api\UrlDuplicateCheckController::class, 'check'])->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $feeds = auth()->user()->feeds()->latest()->get();
        $libraryItems = auth()->user()->libraryItems()
            ->with('mediaFile')
            ->latest()
            ->get();

        return Inertia::render('dashboard', [
            'feeds' => $feeds,
            'libraryItems' => $libraryItems,
        ]);
    })->name('dashboard');

    Route::resource('feeds', FeedController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('feeds/{feed}/edit', [FeedController::class, 'edit'])->name('feeds.edit');

    Route::resource('library', LibraryController::class)->only(['index', 'store', 'destroy']);
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
