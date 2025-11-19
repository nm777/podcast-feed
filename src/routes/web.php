<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RssController;
use App\Http\Controllers\Web\UrlDuplicateCheckController;
use App\Http\Controllers\Web\YouTubeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/rss/{user_guid}/{feed_slug}', [RssController::class, 'show'])->name('rss.show');

Route::get('/files/{file_path}', [MediaController::class, 'show'])->name('files.show')->where('file_path', '.*');

Route::post('check-url-duplicate', [UrlDuplicateCheckController::class, 'check'])->middleware(['auth', 'verified']);
Route::get('youtube/video-info/{videoId}', [YouTubeController::class, 'getVideoInfo'])->middleware(['auth', 'verified']);

Route::middleware(['auth', 'verified', 'approved'])->group(function () {
    Route::get('dashboard', function () {
        $feeds = Auth::user()->feeds()
            ->withCount('items')
            ->latest()
            ->get();
        $libraryItems = Auth::user()->libraryItems()
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

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
    Route::post('users/{user}/reject', [UserManagementController::class, 'reject'])->name('users.reject');
    Route::post('users/{user}/toggle-admin', [UserManagementController::class, 'toggleAdmin'])->name('users.toggle-admin');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
