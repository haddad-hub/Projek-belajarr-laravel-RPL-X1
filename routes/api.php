<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// The API is consumed by the Bonjek web application, which authenticates with
// Laravel's session cookie. The `web` middleware starts that session before the
// auth middleware checks it.
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/customers', [UserController::class, 'customers']);
    Route::get('/customers/{user}', [UserController::class, 'customer']);
    Route::get('/couriers', [UserController::class, 'couriers']);
    Route::get('/couriers/{user}', [UserController::class, 'courier']);
});
