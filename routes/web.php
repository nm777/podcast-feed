<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\RssController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/rss/{user_guid}/{feed_slug}', [RssController::class, 'show'])->name('rss.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $feeds = auth()->user()->feeds()->latest()->get();

        return Inertia::render('dashboard', [
            'feeds' => $feeds,
        ]);
    })->name('dashboard');

    Route::resource('feeds', FeedController::class)->only(['index', 'store', 'destroy']);

    Route::resource('library', LibraryController::class)->only(['index', 'store', 'destroy']);
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
