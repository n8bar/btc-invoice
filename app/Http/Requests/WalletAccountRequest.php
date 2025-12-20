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

    protected function prepareForValidation(): void
    {
        $value = $this->input('bip84_xpub');

        if (is_string($value)) {
            $this->merge([
                'bip84_xpub' => preg_replace('/\s+/', '', $value),
            ]);
        }
    }

    public function rules(): array
    {
        $network = config('wallet.default_network', 'testnet');
        $prefixes = $network === 'mainnet' ? 'xpub|zpub' : 'tpub|vpub';

        return [
            'label' => ['required','string','max:64'],
            'bip84_xpub' => ['required','string','max:255',"regex:/^({$prefixes})[A-Za-z0-9]+$/"],
        ];
    }

    public function messages(): array
    {
        return [
            'bip84_xpub.required' => 'Please paste your wallet account key.',
            'bip84_xpub.regex' => 'That key does not look right. Check you copied the full account public key (no spaces or line breaks).',
        ];
    }
}
