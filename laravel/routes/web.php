<?php

use App\Http\Controllers\AuthEmailCodeController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ManagedAvatarController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RankingsController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\ViewerLibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
Route::post('/auth/email-codes', [AuthEmailCodeController::class, 'store'])->name('auth.email-codes.store');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
Route::post('/password/reset', [PasswordResetController::class, 'store'])->name('password.reset.store');
Route::get('/', HomeController::class)->name('home');
Route::get('/explore', ExploreController::class)->name('explore');
Route::get('/subscriptions', SubscriptionsController::class)->name('subscriptions');
Route::get('/subscriptions/{account}', SubscriptionsController::class)
    ->where('account', '[A-Za-z0-9._-]+')
    ->name('subscriptions.show');
Route::get('/rankings', RankingsController::class)->name('rankings');
Route::get('/videos/{video}', VideoController::class)
    ->whereNumber('video')
    ->name('videos.show');
Route::get('/avatars/{user}/{filename}', ManagedAvatarController::class)
    ->whereNumber('user')
    ->where('filename', '[^/]+')
    ->name('managed-avatar.show');
Route::middleware('auth')->group(function (): void {
    Route::get('/me', [ProfileController::class, 'current'])->name('profile.me');
    Route::get('/me/history', [ViewerLibraryController::class, 'history'])->name('viewer.history');
    Route::get('/me/bookmarks', [ViewerLibraryController::class, 'bookmarks'])->name('viewer.bookmarks');
    Route::get('/me/interactions', [ViewerLibraryController::class, 'interactions'])->name('viewer.interactions');
});
Route::get('/{username}', [ProfileController::class, 'show'])
    ->where('username', '^(?!(?:login|explore|subscriptions|rankings|me)$)[A-Za-z0-9._-]+$')
    ->name('profile.show');
