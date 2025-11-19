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
        return [
            'label' => ['required','string','max:64'],
            'network' => ['required','in:testnet,mainnet'],
            'bip84_xpub' => ['required','string','max:255'],
        ];
    }
}
