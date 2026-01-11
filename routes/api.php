<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    // Tickets routes
    Route::apiResource('tickets', TicketController::class);

    // Additional ticket-specific routes
    Route::prefix('tickets/{ticket}')->group(function () {
        Route::post('/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::post('/complete', [TicketController::class, 'complete'])->name('tickets.complete');
        Route::post('/approve', [TicketController::class, 'approve'])->name('tickets.approve');
        Route::post('/reject', [TicketController::class, 'reject'])->name('tickets.reject');
        Route::get('/comments', [TicketController::class, 'comments'])->name('tickets.comments');
        Route::post('/comments', [TicketController::class, 'storeComment'])->name('tickets.comments.store');
    });

    // Users routes
    Route::apiResource('users', UserController::class);

    // Additional user-specific routes
    Route::prefix('users/{user}')->group(function () {
        Route::get('/tickets', [UserController::class, 'tickets'])->name('users.tickets');
    });
});
