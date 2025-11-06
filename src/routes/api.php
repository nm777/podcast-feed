<?php

use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\LibraryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::apiResource('library', LibraryController::class);
    Route::apiResource('feeds', FeedController::class);
    Route::post('feeds/{feed}/items', [FeedController::class, 'addItems']);
    Route::delete('feeds/{feed}/items', [FeedController::class, 'removeItems']);
});
