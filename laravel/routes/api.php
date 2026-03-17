<?php

use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SystemController;
use Illuminate\Support\Facades\Route;

Route::get('/feed', [FeedController::class, 'index']);
Route::get('/sources', [FeedController::class, 'sources']);
Route::get('/stats', [FeedController::class, 'stats']);
Route::get('/media/{tweetId}', [MediaController::class, 'show']);
Route::get('/health', [SystemController::class, 'health']);
