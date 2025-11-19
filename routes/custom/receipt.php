<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'receipt', 'as' => 'receipt.'], function () {
    Route::post('upload', [\App\Http\Controllers\ReceiptController::class, 'upload']);
    Route::post('update-items', [\App\Http\Controllers\ReceiptController::class, 'updateItems']);
});