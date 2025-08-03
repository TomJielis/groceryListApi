<?php

use App\Http\Controllers\GroceryListItemController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'grocery-list-item', 'as' => 'grocery-list-item.'], function () {

    Route::get('index', [GroceryListItemController::class, 'index']);
    Route::post('store', [GroceryListItemController::class, 'store']);
    Route::post('{listItem}/increase', [GroceryListItemController::class, 'increase']);
    Route::post('{listItem}/decrease', [GroceryListItemController::class, 'decrease']);
    Route::delete('{listItem}/delete', [GroceryListItemController::class, 'delete']);
});
