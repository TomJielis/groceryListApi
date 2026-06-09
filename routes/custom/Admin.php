<?php

use App\Http\Controllers\AdminEmailController;
use App\Http\Controllers\AdminStatsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::get('stats/users', [AdminStatsController::class, 'users']);
    Route::get('stats/items', [AdminStatsController::class, 'items']);
    Route::get('stats/lists', [AdminStatsController::class, 'lists']);
    Route::get('stats/activity', [AdminStatsController::class, 'activity']);
    Route::get('stats/versions', [AdminStatsController::class, 'versions']);
    Route::get('stats/top-items', [AdminStatsController::class, 'topItems']);
    Route::get('stats/spend', [AdminStatsController::class, 'spend']);

    Route::get('users', [AdminStatsController::class, 'usersList']);
    Route::get('users/{id}', [AdminStatsController::class, 'userDetail']);
    Route::post('users/{id}/block', [AdminStatsController::class, 'block']);

    Route::get('emails', [AdminEmailController::class, 'index']);
});
