<?php

use App\Http\Controllers\AdminSupportController;
use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

// Only with an account; people who can't log in mail the support address instead
Route::group(['prefix' => 'support', 'as' => 'support.'], function () {
    Route::post('tickets', [SupportController::class, 'store'])->middleware('throttle:support');
    Route::get('tickets', [SupportController::class, 'index']);
    Route::get('unread-count', [SupportController::class, 'unreadCount']);
    Route::get('tickets/{id}', [SupportController::class, 'show'])->whereNumber('id');
    Route::post('tickets/{id}/messages', [SupportController::class, 'reply'])->whereNumber('id')->middleware('throttle:support');
});

Route::group(['prefix' => 'admin/support', 'as' => 'admin.support.', 'middleware' => 'admin'], function () {
    Route::get('tickets', [AdminSupportController::class, 'index']);
    Route::get('tickets/{id}', [AdminSupportController::class, 'show'])->whereNumber('id');
    Route::post('tickets/{id}/reply', [AdminSupportController::class, 'reply'])->whereNumber('id');
    Route::post('tickets/{id}/close', [AdminSupportController::class, 'close'])->whereNumber('id');
    Route::post('tickets/{id}/reopen', [AdminSupportController::class, 'reopen'])->whereNumber('id');
});
