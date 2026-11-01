<?php

use App\Http\Controllers\InviteLinkController;
use Illuminate\Support\Facades\Route;

Route::post('grocery-list/{id}/invite-link', [InviteLinkController::class, 'storeForList'])->whereNumber('id')->middleware('throttle:share');
Route::post('cookbooks/{id}/invite-link', [InviteLinkController::class, 'storeForCookbook'])->whereNumber('id')->middleware('throttle:share');

Route::group(['prefix' => 'invite-links', 'as' => 'invite-links.'], function () {
    // Also without being logged in: the screen that opens a link says what it's about before logging in
    Route::get('{token}', [InviteLinkController::class, 'show'])->withoutMiddleware('auth:sanctum')->middleware('throttle:60,1');
    Route::post('{token}/accept', [InviteLinkController::class, 'accept']);
    Route::delete('{token}/delete', [InviteLinkController::class, 'delete']);
});
