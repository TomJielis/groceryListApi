<?php

use App\Http\Controllers\RecipeLinkController;
use Illuminate\Support\Facades\Route;

Route::post('recipes/{id}/share-link', [RecipeLinkController::class, 'store'])->whereNumber('id')->middleware('throttle:share');

Route::group(['prefix' => 'recipe-links', 'as' => 'recipe-links.'], function () {
    // Also without being logged in: the app and the website show the recipe before logging in
    Route::get('{token}', [RecipeLinkController::class, 'show'])->withoutMiddleware('auth:sanctum')->middleware('throttle:60,1');
    Route::get('{token}/image', [RecipeLinkController::class, 'image'])->withoutMiddleware('auth:sanctum')->middleware('throttle:120,1');
    Route::post('{token}/save', [RecipeLinkController::class, 'save']);
    Route::delete('{token}/delete', [RecipeLinkController::class, 'delete']);
});
