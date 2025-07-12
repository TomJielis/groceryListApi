<?php

use App\Http\Controllers\ListItemController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'list-item', 'as' => 'list-item.'], function () {

    Route::get('index', [ListItemController::class, 'index']);
    Route::post('store', [ListItemController::class, 'store']);
});
