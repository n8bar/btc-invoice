<?php

namespace App\Services\Blockchain;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MempoolClient
{
    private array $tipHeightCache = [];

    public function __construct(private readonly array $config)
    {
    }

    public function transactions(string $network, string $address): array
    {
        return $this->transactionsForAddresses($network, [$address])[$address] ?? [];
    }

    /**
     * @param  array<int, string>  $addresses
     * @return array<string, array<int, mixed>>
     */
    public function transactionsForAddresses(string $network, array $addresses): array
    {
        $addresses = array_values(array_unique(array_filter(array_map('strval', $addresses))));

        if ($addresses === []) {
            return [];
        }

        $baseUrl = $this->baseUrl($network);
        $responses = Http::pool(function (Pool $pool) use ($addresses, $baseUrl) {
            $requests = [];

            foreach ($addresses as $address) {
                $requests[$address] = $pool
                    ->as($address)
                    ->timeout($this->timeout())
                    ->acceptJson()
                    ->get($baseUrl . '/address/' . $address . '/txs');
            }

            return $requests;
        });

        $transactions = [];

        foreach ($addresses as $address) {
            $response = $responses[$address] ?? null;

            if (! $response || ! $response->ok()) {
                Log::warning('Mempool transactions fetch failed', [
                    'network' => $network,
                    'address' => $address,
                    'status' => $response?->status(),
                    'body' => $response?->body(),
                ]);

                $transactions[$address] = [];
                continue;
            }

            $transactions[$address] = $response->json() ?? [];
        }

        return $transactions;
    }

    public function tipHeight(string $network): ?int
    {
        if (array_key_exists($network, $this->tipHeightCache)) {
            return $this->tipHeightCache[$network];
        }

        $url = $this->baseUrl($network) . '/blocks/tip/height';
        $response = Http::timeout($this->timeout())->get($url);

        if (!$response->ok()) {
            Log::warning('Mempool tip height fetch failed', [
                'network' => $network,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return $this->tipHeightCache[$network] = null;
        }

        $body = trim((string) $response->body());
        if ($body === '') {
            return $this->tipHeightCache[$network] = null;
        }

        return $this->tipHeightCache[$network] = (int) $body;
    }

    private function baseUrl(string $network): string
    {
        $network = strtolower(trim($network));

        $key = match ($network) {
            'mainnet' => 'mainnet_base',
            'testnet' => 'testnet_base',
            'testnet3' => 'testnet3_base',
            'testnet4' => 'testnet4_base',
            default => null,
        };

        if (!$key) {
            throw new \InvalidArgumentException("Unknown mempool base for network {$network}");
        }

        $base = $this->config['mempool'][$key] ?? null;

        if (!$base) {
            throw new \InvalidArgumentException("Unknown mempool base for network {$network}");
        }

        return rtrim($base, '/');
    }

    private function timeout(): float
    {
        return (float) ($this->config['mempool']['timeout'] ?? 8.0);
    }
}
