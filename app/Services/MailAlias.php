<?php

namespace App\Services;

class MailAlias
{
    public function __construct(
        private readonly ?string $domain,
        private readonly bool $enabled
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->domain);
    }

    public function convert(?string $address): ?string
    {
        if (!$this->isEnabled() || $address === null) {
            return $address;
        }

        $trimmed = trim($address);
        if ($trimmed === '' || !str_contains($trimmed, '@')) {
            return $trimmed;
        }

        [$local, $domain] = explode('@', $trimmed, 2);
        $local = trim($local);
        $domain = trim($domain);

        if ($local === '' || $domain === '') {
            return $trimmed;
        }

        $aliasLocal = $this->normalizePart($local);
        $aliasDomainPart = $this->normalizePart($domain, lowercase: true);

        if ($aliasLocal === '' || $aliasDomainPart === '') {
            return $trimmed;
        }

        return "{$aliasLocal}.{$aliasDomainPart}@{$this->domain}";
    }

    private function normalizePart(string $value, bool $lowercase = false): string
    {
        $value = str_replace('@', '.', $value);
        $value = preg_replace('/\s+/', '', $value);
        $value = trim((string) $value, '.');

        if ($lowercase) {
            $value = strtolower($value);
        }

        return $value;
    }
}
