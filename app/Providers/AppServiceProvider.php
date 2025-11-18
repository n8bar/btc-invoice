<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Invoice;
use App\Policies\ClientPolicy;
use App\Policies\InvoicePolicy;
use App\Services\Blockchain\MempoolClient;
use App\Services\MailAlias;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MempoolClient::class, function ($app) {
            return new MempoolClient(config('blockchain'));
        });

        $this->app->singleton(MailAlias::class, function ($app) {
            $config = $app['config']->get('mail.aliasing', []);

            return new MailAlias(
                $config['domain'] ?? null,
                (bool) ($config['enabled'] ?? false)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
