<?php

use App\Http\Controllers\ExploreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RankingsController;
use App\Http\Controllers\SubscriptionsController;
use App\Http\Controllers\UiController;
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
