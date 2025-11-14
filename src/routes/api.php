<?php

use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\UrlDuplicateCheckController;
use App\Http\Controllers\Api\YouTubeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::apiResource('feeds', FeedController::class)->only(['index', 'store']);

    Route::get('library', [LibraryController::class, 'index']);
    Route::post('library', [LibraryController::class, 'store'])->middleware('throttle:10,1');
    Route::post('feeds/{feed}/items', [FeedController::class, 'addItems'])->middleware('throttle:30,1');
    Route::delete('feeds/{feed}/items', [FeedController::class, 'removeItems'])->middleware('throttle:30,1');
    Route::get('youtube/video-info/{videoId}', [YouTubeController::class, 'getVideoInfo'])->middleware('throttle:60,1');
    Route::post('check-url', [UrlDuplicateCheckController::class, 'check'])->middleware('throttle:60,1');
});
