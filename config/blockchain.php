<?php

return [
    'confirmations_required' => (int) env('BLOCKCHAIN_CONFIRMATIONS_REQUIRED', 1),
    'mempool' => [
        'timeout' => (float) env('MEMPOOL_HTTP_TIMEOUT', 8.0),
        'mainnet_base' => env('MEMPOOL_MAINNET_URL', 'https://mempool.space/api'),
        'testnet_base' => env('MEMPOOL_TESTNET_URL', 'https://mempool.space/testnet/api'),
    ],
];
