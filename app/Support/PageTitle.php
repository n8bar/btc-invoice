<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageTitle
{
    public static function resolve(?Request $request): string
    {
        if (!$request || !$request->route()) {
            return '';
        }

        $routeName = (string) ($request->route()->getName() ?? '');
        if ($routeName === '') {
            return '';
        }

        return match (true) {
            $routeName === 'dashboard' => 'Dashboard',
            $routeName === 'help' => 'Notes',

            $routeName === 'profile.edit' => 'Profile',
            $routeName === 'settings.invoice.edit' => 'Settings',
            $routeName === 'wallet.settings.edit' => 'Wallet',

            $routeName === 'clients.index' => 'Clients',
            $routeName === 'clients.create' => 'New Client',
            $routeName === 'clients.edit', $routeName === 'clients.show' => self::clientTitle($request, 'Client'),
            $routeName === 'clients.trash' => 'Clients Trash',

            $routeName === 'invoices.index' => 'Invoices',
            $routeName === 'invoices.create' => 'New Invoice',
            $routeName === 'invoices.edit', $routeName === 'invoices.show', $routeName === 'invoices.print' => self::invoiceTitle($request, 'Invoice'),
            $routeName === 'invoices.trash' => 'Invoices Trash',

            $routeName === 'login' => 'Login',
            $routeName === 'register' => 'Register',
            $routeName === 'password.request' => 'Forgot Password',
            $routeName === 'password.reset' => 'Reset Password',
            $routeName === 'verification.notice' => 'Verify Email',
            $routeName === 'password.confirm' => 'Confirm Password',

            default => Str::of(str_replace('.', ' ', $routeName))->title()->toString(),
        };
    }

    private static function clientTitle(Request $request, string $fallback): string
    {
        $client = $request->route('client');
        if ($client instanceof Client) {
            return (string) $client->name;
        }

        if (is_object($client) && isset($client->name)) {
            return (string) $client->name;
        }

        return $fallback;
    }

    private static function invoiceTitle(Request $request, string $fallback): string
    {
        $invoice = $request->route('invoice');
        if ($invoice instanceof Invoice) {
            return 'Invoice: ' . $invoice->number;
        }

        if (is_object($invoice) && isset($invoice->number)) {
            return 'Invoice: ' . (string) $invoice->number;
        }

        return $fallback;
    }
}

