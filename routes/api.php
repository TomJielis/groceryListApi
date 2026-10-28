<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PushTokenController;
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

Route::post('/register', [AuthController::class, 'register'])->withoutMiddleware('auth:sanctum')->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->withoutMiddleware('auth:sanctum')->middleware('throttle:login');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->withoutMiddleware('auth:sanctum')->middleware('throttle:password-reset');
Route::post('/set-new-password', [AuthController::class, 'setNewPassword'])->withoutMiddleware('auth:sanctum');
Route::post('/check-reset-link', [AuthController::class, 'checkResetLink'])->withoutMiddleware('auth:sanctum');
Route::post('/me', [AuthController::class, 'me']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/push-tokens', [PushTokenController::class, 'store']);
Route::delete('/push-tokens/current', [PushTokenController::class, 'destroyCurrent']);
Route::post('/update-language', [AuthController::class, 'updateLanguage']);
Route::post('/approve-terms', [AuthController::class, 'approveTerms']);
Route::post('/update', [AuthController::class, 'update']);
Route::post('/deactivate', [AuthController::class, 'deactivate']);
Route::get('/verify-email/{hash}', [AuthController::class, 'verifyUser'])->withoutMiddleware('auth:sanctum');

// Lets the app tell "server down" from "no internet". Answers 503 in maintenance mode (php artisan down),
// unlike Laravel's /up, which keeps answering 200.
Route::get('/status', fn () => response()->json(['status' => 'ok']))->withoutMiddleware('auth:sanctum');
