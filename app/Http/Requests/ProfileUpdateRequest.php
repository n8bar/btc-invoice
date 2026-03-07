<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'show_invoice_ids' => ['nullable', 'boolean'],
            'auto_receipt_emails' => ['nullable', 'boolean'],
            'billing_name' => ['nullable','string','max:255'],
            'billing_email' => ['nullable','email','max:255'],
            'billing_phone' => ['nullable','string','max:255'],
            'billing_address' => ['nullable','string','max:2000'],
            'invoice_footer_note' => ['nullable','string','max:1000'],
            'branding_heading' => ['nullable','string','max:255'],
            'invoice_default_description' => ['nullable','string','max:2000'],
            'invoice_default_terms_days' => ['nullable','integer','min:0','max:365'],
        ];
    }
}
