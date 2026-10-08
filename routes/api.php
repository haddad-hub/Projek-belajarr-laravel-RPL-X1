<?php

use App\Http\Controllers\Api\YojekController;
use Illuminate\Support\Facades\Route;

// The web middleware supplies CSRF protection; API identity comes from
// the per-tab Yojek token rather than the browser's shared login cookie.
Route::middleware(['web'])->group(function () {
    Route::post('/yojek/register', [YojekController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/yojek/login', [YojekController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/yojek/state', [YojekController::class, 'state']);
    Route::post('/yojek/token', [YojekController::class, 'issueToken']);
    Route::post('/yojek/profile', [YojekController::class, 'updateProfile']);
    Route::post('/yojek/logout', [YojekController::class, 'logout']);
    Route::post('/yojek/orders', [YojekController::class, 'createOrder']);
    Route::post('/yojek/orders/{order}/accept', [YojekController::class, 'acceptOrder']);
    Route::post('/yojek/orders/{order}/complete', [YojekController::class, 'completeOrder']);
    Route::post('/yojek/orders/{order}/confirm', [YojekController::class, 'confirmOrder']);
    Route::post('/yojek/orders/{order}/rate', [YojekController::class, 'rateOrder']);
    Route::post('/yojek/courier/availability', [YojekController::class, 'updateAvailability']);
    Route::post('/yojek/couriers/{user}/disabled', [YojekController::class, 'setCourierDisabled']);
});
