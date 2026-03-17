<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/{any?}', HomeController::class)->where('any', '.*');
