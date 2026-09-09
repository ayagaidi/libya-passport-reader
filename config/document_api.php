<?php

$clients = [];

foreach (explode(',', (string) env('DOCUMENT_API_CLIENTS', '')) as $entry) {
    $entry = trim($entry);

    if ($entry === '') {
        continue;
    }

    [$id, $keyHash, $rateLimit] = array_pad(explode(':', $entry, 3), 3, null);
    $id = trim((string) $id);
    $keyHash = strtolower(trim((string) $keyHash));
    $rateLimit = (int) ($rateLimit ?: env('DOCUMENT_API_DEFAULT_RATE_LIMIT', 60));

    if ($id === '' || ! preg_match('/^[a-f0-9]{64}$/', $keyHash)) {
        continue;
    }

    $clients[$id] = [
        'key_hash' => $keyHash,
        'rate_limit_per_minute' => max(1, $rateLimit),
    ];
}

return [
    'enabled' => (bool) env('DOCUMENT_API_AUTH_ENABLED', false),
    'header' => env('DOCUMENT_API_KEY_HEADER', 'X-API-Key'),
    'default_rate_limit' => max(1, (int) env('DOCUMENT_API_DEFAULT_RATE_LIMIT', 60)),
    'clients' => $clients,
];
