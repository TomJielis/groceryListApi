<?php

use App\Http\Controllers\CookbookController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'cookbooks', 'as' => 'cookbooks.'], function () {
    Route::get('index', [CookbookController::class, 'index']);
    Route::post('store', [CookbookController::class, 'store']);
    Route::post('favorite', [CookbookController::class, 'favorite']);
    Route::post('{id}/update', [CookbookController::class, 'update'])->whereNumber('id');
    Route::delete('{id}/delete', [CookbookController::class, 'delete'])->whereNumber('id');
    Route::delete('{id}/members/{userId}', [CookbookController::class, 'unshare'])->whereNumber('id')->whereNumber('userId');
});
