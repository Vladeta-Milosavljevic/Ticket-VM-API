<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    // Categories routes
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('/categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
    Route::post('/categories/{category}/reactivate', [CategoryController::class, 'reactivate'])->name('categories.reactivate');

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

    // Logout route - revokes the bearer token
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Attachments routes
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Users routes
    Route::apiResource('users', UserController::class);
    // Additional user-specific routes
    Route::prefix('users/{user}')->group(function () {
        Route::get('/tickets', [UserController::class, 'tickets'])->name('users.tickets');
        Route::post('/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();
    });

});
