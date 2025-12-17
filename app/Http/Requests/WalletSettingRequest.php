<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $network = config('wallet.default_network', 'testnet');
        $prefixes = $network === 'mainnet' ? 'xpub|zpub' : 'tpub|vpub';

        return [
            'bip84_xpub' => ['required', 'string', 'max:256', "regex:/^({$prefixes})[A-Za-z0-9]+$/"],
        ];
    }

    public function messages(): array
    {
        return [
            'bip84_xpub.regex' => 'Enter a valid BIP84 wallet key for the configured network.',
        ];
    }
}
