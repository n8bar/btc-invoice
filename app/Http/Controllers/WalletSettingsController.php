<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletAccountRequest;
use App\Http\Requests\WalletKeyPreviewRequest;
use App\Http\Requests\WalletSettingRequest;
use App\Models\UserWalletAccount;
use App\Services\HdWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class WalletSettingsController extends Controller
{
    private const MAX_ADDITIONAL_ACCOUNTS = 3;

    public function edit(Request $request)
    {
        $network = Config::get('wallet.default_network', 'testnet');

        return view('wallet.settings', [
            'wallet' => $request->user()->walletSetting,
            'walletAccounts' => $request->user()->walletAccounts()
                ->orderBy('label')
                ->get(),
            'maxAdditionalWallets' => self::MAX_ADDITIONAL_ACCOUNTS,
            'defaultNetwork' => $network,
        ]);
    }

    public function validateKey(WalletKeyPreviewRequest $request)
    {
        $network = Config::get('wallet.default_network', 'testnet');
        $xpub = $request->validated()['bip84_xpub'];

        try {
            $address = app(HdWallet::class)->deriveAddress(
                $xpub,
                0,
                $network
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'We could not read that key. Confirm it matches the configured network and try again.',
            ], 422);
        }

        return response()->json([
            'address' => $address,
        ]);
    }

    public function update(WalletSettingRequest $request)
    {
        $user = $request->user();
        $network = Config::get('wallet.default_network', 'testnet');

        $payload = $request->validated();
        $payload['network'] = $network;

        // Validate the xpub by deriving a single address to catch bad keys early.
        try {
            app(HdWallet::class)->deriveAddress(
                $payload['bip84_xpub'],
                0,
                $payload['network']
            );
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['bip84_xpub' => 'We could not read that key. Confirm it matches the configured network and try again.'])
                ->withInput();
        }

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
        $network = Config::get('wallet.default_network', 'testnet');

        if ($user->walletAccounts()->count() >= self::MAX_ADDITIONAL_ACCOUNTS) {
            return back()
                ->withErrors(['label' => 'You have reached the additional wallet limit.'], 'walletAccount')
                ->withInput();
        }

        $payload = $request->validated();
        $payload['network'] = $network;

        try {
            app(HdWallet::class)->deriveAddress(
                $payload['bip84_xpub'],
                0,
                $payload['network']
            );
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['bip84_xpub' => 'We could not read that key. Confirm it matches the configured network and try again.'], 'walletAccount')
                ->withInput();
        }

        $user->walletAccounts()->create($payload);

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
