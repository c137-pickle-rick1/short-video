<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\RankingsController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\UiController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\ProfileAvatarController;
use App\Http\Controllers\Api\UserFollowController;
use App\Http\Controllers\Api\VideoBookmarkController;
use App\Http\Controllers\Api\VideoCommentController;
use App\Http\Controllers\Api\VideoLikeController;
use App\Http\Controllers\Api\VideoViewController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
Route::get('/ui', UiController::class)->name('ui');
Route::get('/', HomeController::class)->name('home');
Route::get('/explore', ExploreController::class)->name('explore');
Route::get('/subscriptions', SubscriptionsController::class)->name('subscriptions');
Route::get('/rankings', RankingsController::class)->name('rankings');
Route::middleware('auth')->get('/me', ProfileController::class)->name('profile');

Route::prefix('api')->group(function (): void {
    Route::get('/feed', [FeedController::class, 'index']);
    Route::get('/sources', [FeedController::class, 'sources']);
    Route::get('/stats', [FeedController::class, 'stats']);
    Route::get('/rankings/creators', [FeedController::class, 'creators']);
    Route::post('/profile/avatar', [ProfileAvatarController::class, 'store']);
    Route::post('/users/{user}/follow', [UserFollowController::class, 'store']);
    Route::delete('/users/{user}/follow', [UserFollowController::class, 'destroy']);
    Route::post('/videos/{video}/views', [VideoViewController::class, 'store']);
    Route::get('/videos/{video}/comments', [VideoCommentController::class, 'index']);
    Route::post('/videos/{video}/comments', [VideoCommentController::class, 'store']);
    Route::post('/videos/{video}/likes', [VideoLikeController::class, 'store']);
    Route::delete('/videos/{video}/likes', [VideoLikeController::class, 'destroy']);
    Route::post('/videos/{video}/bookmarks', [VideoBookmarkController::class, 'store']);
    Route::delete('/videos/{video}/bookmarks', [VideoBookmarkController::class, 'destroy']);
});
