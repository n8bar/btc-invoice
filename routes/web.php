<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

// Simple health probe for uptime checks / container orchestrators
Route::get('/health', fn () => response()->json(['ok' => true]));

// Landing page (keep Breeze welcome)
Route::get('/', fn () => view('welcome'));

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Breeze dashboard
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Breeze profile management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // App resources
    Route::resource('clients', ClientController::class);
    Route::resource('invoices', InvoiceController::class);
});

// Breeze auth scaffolding (login, register, password reset, etc.)
require __DIR__.'/auth.php';
