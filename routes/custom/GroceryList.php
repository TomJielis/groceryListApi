<?php

use App\Http\Controllers\GroceryListController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'grocery-list', 'as' => 'grocery-list.'], function () {
    Route::get('index', [GroceryListController::class, 'index']);
    Route::post('store', [GroceryListController::class, 'store']);
    Route::post('share', [GroceryListController::class, 'share']);
    Route::delete('{groceryList}/delete', [GroceryListController::class, 'delete']);
});