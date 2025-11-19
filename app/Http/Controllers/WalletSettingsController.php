<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletAccountRequest;
use App\Http\Requests\WalletSettingRequest;
use App\Models\UserWalletAccount;
use Illuminate\Http\Request;

class WalletSettingsController extends Controller
{
    private const MAX_ADDITIONAL_ACCOUNTS = 3;

    public function edit(Request $request)
    {
        return view('wallet.settings', [
            'wallet' => $request->user()->walletSetting,
            'walletAccounts' => $request->user()->walletAccounts()
                ->orderBy('label')
                ->get(),
            'maxAdditionalWallets' => self::MAX_ADDITIONAL_ACCOUNTS,
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

    public function storeAccount(WalletAccountRequest $request)
    {
        $user = $request->user();

        if ($user->walletAccounts()->count() >= self::MAX_ADDITIONAL_ACCOUNTS) {
            return back()
                ->withErrors(['label' => 'You have reached the additional wallet limit.'])
                ->withInput();
        }

        $user->walletAccounts()->create($request->validated());

        return redirect()->route('wallet.settings.edit')
            ->with('status', 'Additional wallet saved.');
    }

    public function destroyAccount(Request $request, UserWalletAccount $account)
    {
        if ($account->user_id !== $request->user()->id) {
            abort(403);
        }

        $account->delete();

        return redirect()->route('wallet.settings.edit')
            ->with('status', 'Wallet removed.');
    }
}
