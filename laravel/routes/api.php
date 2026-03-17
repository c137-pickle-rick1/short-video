<?php

use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SystemController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{tweetId}', [MediaController::class, 'show']);
Route::get('/health', [SystemController::class, 'health']);
