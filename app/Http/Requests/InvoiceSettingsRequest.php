<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branding_heading' => ['nullable','string','max:255'],
            'billing_name' => ['nullable','string','max:255'],
            'billing_email' => ['nullable','email','max:255'],
            'billing_phone' => ['nullable','string','max:255'],
            'billing_address' => ['nullable','string','max:2000'],
            'invoice_footer_note' => ['nullable','string','max:1000'],
            'invoice_default_description' => ['nullable','string','max:2000'],
            'invoice_default_terms_days' => ['nullable','integer','min:0','max:365'],
        ];
    }
}
