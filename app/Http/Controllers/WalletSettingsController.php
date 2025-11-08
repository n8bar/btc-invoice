<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletSettingRequest;
use Illuminate\Http\Request;

class WalletSettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('wallet.settings', [
            'wallet' => $request->user()->walletSetting,
        ]);
    }

    public function update(WalletSettingRequest $request)
    {
        $user = $request->user();

        $payload = $request->validated();

        $wallet = $user->walletSetting()->updateOrCreate(['user_id' => $user->id], [
            'network' => $payload['network'],
            'bip84_xpub' => $payload['bip84_xpub'],
            'onboarded_at' => now(),
        ]);

        if ($wallet->wasRecentlyCreated || $wallet->wasChanged('bip84_xpub')) {
            $wallet->next_derivation_index = 0;
            $wallet->save();
        }

        return redirect()->route('wallet.settings.edit')
            ->with('status', 'Wallet settings saved.');
    }
}
