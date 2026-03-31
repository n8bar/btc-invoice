<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mail_brand_name' => ['nullable', 'string', 'max:80'],
            'mail_brand_tagline' => ['nullable', 'string', 'max:120'],
            'mail_footer_blurb' => ['nullable', 'string', 'max:280'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mail_brand_name' => $this->normalizeOptionalString($this->input('mail_brand_name')),
            'mail_brand_tagline' => $this->normalizeOptionalString($this->input('mail_brand_tagline')),
            'mail_footer_blurb' => $this->normalizeOptionalString($this->input('mail_footer_blurb')),
        ]);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
