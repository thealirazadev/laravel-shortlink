<?php

use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{code}', [RedirectController::class, 'show'])
    ->where('code', '[A-Za-z0-9]+')
    ->middleware('throttle:redirect');
