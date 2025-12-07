<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletAccountRequest extends FormRequest
{
    protected $errorBag = 'walletAccount';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $network = config('wallet.default_network', 'testnet');
        $prefixes = $network === 'mainnet' ? 'xpub|ypub|zpub' : 'tpub|vpub';

        return [
            'label' => ['required','string','max:64'],
            'bip84_xpub' => ['required','string','max:255',"regex:/^({$prefixes})[A-Za-z0-9]+$/"],
        ];
    }

    public function messages(): array
    {
        return [
            'bip84_xpub.regex' => 'Enter a valid BIP84 wallet key for the configured network.',
        ];
    }
}
