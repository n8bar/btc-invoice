<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\WalletSettingsController;
use App\Http\Controllers\InvoiceSettingsController;
use App\Http\Controllers\InvoicePaymentNoteController;
use App\Http\Controllers\InvoiceDeliveryController;
use App\Http\Controllers\InvoicePaymentAdjustmentController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

// Simple health probe for uptime checks / container orchestrators
Route::get('/health', fn () => response()->json(['ok' => true]));

// Landing page (keep Breeze welcome)
Route::get('/', fn () => view('welcome'));

// Public, tokenized print view (no auth)
Route::get('p/{token}', [InvoiceController::class, 'publicPrint'])
    ->name('invoices.public-print');

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
    Route::get('/settings/invoice', [InvoiceSettingsController::class, 'edit'])->name('settings.invoice.edit');
    Route::patch('/settings/invoice', [InvoiceSettingsController::class, 'update'])->name('settings.invoice.update');
    Route::get('/wallet/settings', [WalletSettingsController::class, 'edit'])->name('wallet.settings.edit');
    Route::post('/wallet/settings', [WalletSettingsController::class, 'update'])->name('wallet.settings.update');
    Route::post('/wallet/settings/accounts', [WalletSettingsController::class, 'storeAccount'])
        ->name('wallet.settings.accounts.store');
    Route::delete('/wallet/settings/accounts/{account}', [WalletSettingsController::class, 'destroyAccount'])
        ->whereNumber('account')->name('wallet.settings.accounts.destroy');

    // Clients - custom actions MUST be before the resource
    Route::get('clients/trash', [ClientController::class, 'trash'])->name('clients.trash');
    Route::patch('clients/{clientId}/restore', [ClientController::class, 'restore'])
        ->whereNumber('clientId')->name('clients.restore');
    Route::delete('clients/{clientId}/force', [ClientController::class, 'forceDestroy'])
        ->whereNumber('clientId')->name('clients.force-destroy');

    //BTC-USD Rate
    Route::get('invoices/rate/current', [\App\Http\Controllers\InvoiceController::class, 'currentRate'])
        ->name('invoices.rate');
    Route::post('invoices/rate/refresh', [\App\Http\Controllers\InvoiceController::class, 'refreshRate'])
        ->name('invoices.rate.refresh');

    // Invoices - custom actions MUST be before the resource
    Route::get('invoices/trash', [InvoiceController::class, 'trash'])->name('invoices.trash');
    Route::patch('invoices/{invoiceId}/restore', [InvoiceController::class, 'restore'])
        ->whereNumber('invoiceId')->name('invoices.restore');
    Route::patch('invoices/{invoice}/status/{action}', [InvoiceController::class, 'setStatus'])
        ->where(['action' => 'sent|void|draft'])
        ->name('invoices.set-status');
    Route::delete('invoices/{invoiceId}/force', [InvoiceController::class, 'forceDestroy'])
        ->whereNumber('invoiceId')->name('invoices.force-destroy');

    //Printing
    Route::get('invoices/{invoice}/print', [\App\Http\Controllers\InvoiceController::class, 'print'])
        ->name('invoices.print');

    //Sharing
    Route::patch('invoices/{invoice}/share/enable',  [InvoiceController::class, 'enableShare'])
        ->name('invoices.share.enable');
    Route::patch('invoices/{invoice}/share/disable', [InvoiceController::class, 'disableShare'])
        ->name('invoices.share.disable');
    Route::patch('invoices/{invoice}/share/rotate', [InvoiceController::class, 'rotateShare'])
        ->name('invoices.share.rotate');

    Route::post('invoices/{invoice}/deliver', [InvoiceDeliveryController::class, 'store'])
        ->name('invoices.deliver');
    Route::patch('invoices/{invoice}/payments/{payment}/note', [InvoicePaymentNoteController::class, 'update'])
        ->name('invoices.payments.note');
    Route::post('invoices/{invoice}/payments/adjustments', [InvoicePaymentAdjustmentController::class, 'store'])
        ->name('invoices.payments.adjustments.store');


    // Standard CRUD
    Route::resource('clients', ClientController::class);
    Route::resource('invoices', InvoiceController::class);
});

// Breeze auth scaffolding (login, register, password reset, etc.)
require __DIR__.'/auth.php';
