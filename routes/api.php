<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::group(['as' => 'api.'], function () {
    foreach (File::Files(__DIR__ . '/custom') as $file) {
        require $file->getPathname();
    }
});

Route::post('/register', [AuthController::class, 'register'])->withoutMiddleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'login'])->withoutMiddleware('auth:sanctum');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->withoutMiddleware('auth:sanctum');
Route::post('/set-new-password', [AuthController::class, 'setNewPassword'])->withoutMiddleware('auth:sanctum');
Route::post('/me', [AuthController::class, 'me']);