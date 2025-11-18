<?php

namespace App\Providers;

use App\Events\InvoicePaid;
use App\Listeners\SendInvoiceReceipt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Event::listen(InvoicePaid::class, SendInvoiceReceipt::class);
    }
}
