<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::resource('posts', PostController::class)
    ->only([
        'index',
        'show',
    ]);

Route::resource('posts', PostController::class)
    ->middleware('auth')
    ->except([
        'index',
        'show',
    ]);

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
