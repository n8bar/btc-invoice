<?php

namespace App\Services\Blockchain;

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
        $url = $this->baseUrl($network) . '/address/' . $address . '/txs';
        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->get($url);

        if (!$response->ok()) {
            Log::warning('Mempool transactions fetch failed', [
                'network' => $network,
                'address' => $address,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        return $response->json() ?? [];
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
        $key = strtolower($network) . '_base';
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
