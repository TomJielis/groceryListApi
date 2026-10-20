<?php

use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\UserStatsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
    Route::get('stats', [UserStatsController::class, 'stats']);
    Route::get('notification-settings', [NotificationSettingsController::class, 'show']);
    Route::post('notification-settings', [NotificationSettingsController::class, 'update']);
});
