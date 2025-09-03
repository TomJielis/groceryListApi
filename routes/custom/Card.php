<?php

use App\Http\Controllers\CardController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'cards', 'as' => 'cards.'], function () {
    Route::get('/index', [CardController::class, 'index']);
    Route::post('/store', [CardController::class, 'store']);
});