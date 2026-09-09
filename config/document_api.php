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

$publicDemoClientId = trim((string) env('DOCUMENT_API_PUBLIC_DEMO_CLIENT_ID', ''));
$publicDemoKeyHash = strtolower(trim((string) env('DOCUMENT_API_PUBLIC_DEMO_KEY_HASH', '')));
$publicDemoRateLimit = max(1, (int) env('DOCUMENT_API_PUBLIC_DEMO_RATE_LIMIT', 10));

if ($publicDemoClientId !== '' && preg_match('/^[a-f0-9]{64}$/', $publicDemoKeyHash)) {
    $clients[$publicDemoClientId] = [
        'key_hash' => $publicDemoKeyHash,
        'rate_limit_per_minute' => $publicDemoRateLimit,
    ];
}

return [
    'enabled' => (bool) env('DOCUMENT_API_AUTH_ENABLED', false),
    'header' => env('DOCUMENT_API_KEY_HEADER', 'X-API-Key'),
    'default_rate_limit' => max(1, (int) env('DOCUMENT_API_DEFAULT_RATE_LIMIT', 60)),
    'clients' => $clients,
    'public_demo_client_id' => $publicDemoClientId,
    'public_demo_global_rate_limit' => max(1, (int) env('DOCUMENT_API_PUBLIC_DEMO_GLOBAL_RATE_LIMIT', 120)),
];
