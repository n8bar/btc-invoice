<?php

use Illuminate\Support\Str;

$emails = preg_split('/[\s,]+/', (string) env('SUPPORT_AGENT_EMAILS', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

return [
    'agent_emails' => array_values(array_filter(array_map(
        static fn (string $email): ?string => ($normalized = Str::lower(trim($email))) !== '' ? $normalized : null,
        $emails,
    ))),
    'grant_hours' => max((int) env('SUPPORT_ACCESS_HOURS', 72), 1),
    'terms_version' => (string) env('SUPPORT_ACCESS_TERMS_VERSION', 'v1'),
];
