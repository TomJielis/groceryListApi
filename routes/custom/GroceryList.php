<?php

use App\Http\Controllers\GroceryListController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'grocery-list', 'as' => 'grocery-list.'], function () {
    Route::get('index', [GroceryListController::class, 'index']);
    Route::get('pending', [GroceryListController::class, 'pending']);
    Route::post('store', [GroceryListController::class, 'store']);
    Route::post('share', [GroceryListController::class, 'share']);
    Route::post('favorite', [GroceryListController::class, 'favorite']);
    Route::delete('{groceryList}/delete', [GroceryListController::class, 'delete']);
});