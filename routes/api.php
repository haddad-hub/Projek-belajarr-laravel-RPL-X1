<?php

use App\Http\Controllers\Api\YojekController;
use Illuminate\Support\Facades\Route;

// Yojek uses the Laravel session cookie. The controller also accepts the
// dedicated admin session because the admin dashboard has a separate login.
Route::middleware(['web'])->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
])->group(function () {
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
