<?php

use App\Http\Controllers\WeekMenuController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'week-menus', 'as' => 'week-menus.'], function () {
    Route::get('index', [WeekMenuController::class, 'index']);
    Route::post('store', [WeekMenuController::class, 'store']);
    Route::post('{id}/update', [WeekMenuController::class, 'update'])->whereNumber('id');
    Route::delete('{id}/delete', [WeekMenuController::class, 'delete'])->whereNumber('id');
    Route::delete('{id}/members/{userId}', [WeekMenuController::class, 'removeMember'])->whereNumber('id')->whereNumber('userId');
    Route::get('{id}/entries', [WeekMenuController::class, 'entries'])->whereNumber('id');
    Route::post('{id}/entries', [WeekMenuController::class, 'storeEntry'])->whereNumber('id');
    Route::post('{id}/entries/added-to-list', [WeekMenuController::class, 'addedToList'])->whereNumber('id');
    Route::post('{id}/entries/{entryId}/update', [WeekMenuController::class, 'updateEntry'])->whereNumber('id')->whereNumber('entryId');
    Route::delete('{id}/entries/{entryId}/delete', [WeekMenuController::class, 'deleteEntry'])->whereNumber('id')->whereNumber('entryId');
});
