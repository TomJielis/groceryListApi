<?php

use App\Http\Controllers\UserStatsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
    Route::get('stats', [UserStatsController::class, 'stats']);
});
