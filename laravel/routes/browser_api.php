<?php

use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\ProfileAvatarController;
use App\Http\Controllers\Api\ProfileUpdateController;
use App\Http\Controllers\Api\UserFollowController;
use App\Http\Controllers\Api\VideoBookmarkController;
use App\Http\Controllers\Api\VideoCommentController;
use App\Http\Controllers\Api\VideoHistoryController;
use App\Http\Controllers\Api\VideoLikeController;
use App\Http\Controllers\Api\VideoUploadController;
use App\Http\Controllers\Api\VideoViewController;
use Illuminate\Support\Facades\Route;

Route::get('/feed', [FeedController::class, 'index']);
Route::get('/sources', [FeedController::class, 'sources']);
Route::get('/stats', [FeedController::class, 'stats']);
Route::get('/rankings/creators', [FeedController::class, 'creators']);
Route::post('/profile', [ProfileUpdateController::class, 'store']);
Route::post('/profile/avatar', [ProfileAvatarController::class, 'store']);
Route::post('/users/{user}/follow', [UserFollowController::class, 'store']);
Route::delete('/users/{user}/follow', [UserFollowController::class, 'destroy']);
Route::post('/videos', [VideoUploadController::class, 'store']);
Route::post('/videos/{video}/views', [VideoViewController::class, 'store']);
Route::get('/videos/{video}/comments', [VideoCommentController::class, 'index']);
Route::post('/videos/{video}/comments', [VideoCommentController::class, 'store']);
Route::delete('/videos/{video}/comments/{comment}', [VideoCommentController::class, 'destroy']);
Route::post('/videos/{video}/likes', [VideoLikeController::class, 'store']);
Route::delete('/videos/{video}/likes', [VideoLikeController::class, 'destroy']);
Route::post('/videos/{video}/bookmarks', [VideoBookmarkController::class, 'store']);
Route::delete('/videos/{video}/bookmarks', [VideoBookmarkController::class, 'destroy']);
Route::delete('/history', [VideoHistoryController::class, 'clear']);
Route::delete('/videos/{video}/history', [VideoHistoryController::class, 'destroy']);
