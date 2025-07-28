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


route::get('create-csrf-token',[AuthController::class, 'createCsrfToken']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password-reset', [AuthController::class, 'passwordReset']);
Route::post('/valid-code', [AuthController::class, 'validCode']);
Route::post('/set-new-password', [AuthController::class, 'setNewPassword']);
