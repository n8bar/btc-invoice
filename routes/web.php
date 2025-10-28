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

    // Clients - custom actions MUST be before the resource
    Route::get('clients/trash', [ClientController::class, 'trash'])->name('clients.trash');
    Route::patch('clients/{clientId}/restore', [ClientController::class, 'restore'])
        ->whereNumber('clientId')->name('clients.restore');
    Route::delete('clients/{clientId}/force', [ClientController::class, 'forceDestroy'])
        ->whereNumber('clientId')->name('clients.force-destroy');

    //BTC-USD Rate
    Route::get('invoices/rate/current', [\App\Http\Controllers\InvoiceController::class, 'currentRate'])
        ->name('invoices.rate');

    // Invoices - custom actions MUST be before the resource
    Route::get('invoices/trash', [InvoiceController::class, 'trash'])->name('invoices.trash');
    Route::patch('invoices/{invoiceId}/restore', [InvoiceController::class, 'restore'])
        ->whereNumber('invoiceId')->name('invoices.restore');
    Route::patch('invoices/{invoice}/status/{action}', [InvoiceController::class, 'setStatus'])
        ->where(['action' => 'sent|paid|void|draft'])
        ->name('invoices.set-status');
    Route::delete('invoices/{invoiceId}/force', [InvoiceController::class, 'forceDestroy'])
        ->whereNumber('invoiceId')->name('invoices.force-destroy');


    // Standard CRUD
    Route::resource('clients', ClientController::class);
    Route::resource('invoices', InvoiceController::class);
});

// Breeze auth scaffolding (login, register, password reset, etc.)
require __DIR__.'/auth.php';
