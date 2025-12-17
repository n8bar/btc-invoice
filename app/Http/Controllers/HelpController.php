<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HelpController extends Controller
{
    public function __invoke(Request $request)
    {
        $from = $request->query('from');

        $backLink = null;
        $knownSources = [
            'wallet-settings' => [
                'label' => 'Back to Wallet Settings',
                'url' => route('wallet.settings.edit'),
            ],
        ];

        if (is_string($from) && array_key_exists($from, $knownSources)) {
            $backLink = $knownSources[$from];
        }

        return response()
            ->view('help.index', [
                'backLink' => $backLink,
            ]);
    }
}
