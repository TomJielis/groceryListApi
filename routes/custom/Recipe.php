<?php

use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'recipes', 'as' => 'recipes.'], function () {
    Route::get('index', [RecipeController::class, 'index']);
    Route::post('store', [RecipeController::class, 'store']);
    Route::get('{id}', [RecipeController::class, 'show'])->whereNumber('id');
    Route::post('{id}/update', [RecipeController::class, 'update'])->whereNumber('id');
    Route::delete('{id}/delete', [RecipeController::class, 'delete'])->whereNumber('id');
    Route::post('{id}/favorite', [RecipeController::class, 'favorite'])->whereNumber('id');
    Route::post('{id}/add-to-list', [RecipeController::class, 'addToList'])->whereNumber('id');
    Route::post('{id}/image', [RecipeController::class, 'storeImage'])->whereNumber('id');
    Route::delete('{id}/image/delete', [RecipeController::class, 'deleteImage'])->whereNumber('id');
    Route::get('{id}/image', [RecipeController::class, 'image'])->whereNumber('id');
});
