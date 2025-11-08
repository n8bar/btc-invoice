<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class HdWallet
{
    public function deriveAddress(string $xpub, int $index, string $network = 'testnet'): string
    {
        $script = base_path('node_scripts/derive-address.js');
        if (!file_exists($script)) {
            throw new RuntimeException('Wallet derivation script missing.');
        }

        $result = Process::timeout(30)->run([
            'node',
            $script,
            $xpub,
            (string) $index,
            $network,
        ]);

        if (!$result->successful()) {
            throw new RuntimeException('Address derivation failed: ' . trim($result->errorOutput() ?: $result->output()));
        }

        $payload = json_decode($result->output(), true);
        if (!is_array($payload) || empty($payload['address'])) {
            throw new RuntimeException('Address derivation returned invalid payload.');
        }

        return (string) $payload['address'];
    }
}
