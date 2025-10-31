<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
class BtcRate
{
    const CACHE_KEY = 'btc:rate:usd';
    const TTL = 3600; // seconds

    /**
     * Returns ['rate_usd' => float, 'source' => 'coinbase', 'as_of' => Carbon] or null.
     */
    public static function current(): ?array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function () {
            try {
                $res = Http::timeout(8)
                    ->acceptJson()
                    ->get('https://api.coinbase.com/v2/exchange-rates', ['currency' => 'BTC']);

                $usd = data_get($res->json(), 'data.rates.USD');
                if (!$usd) {
                    return null;
                }

                return [
                    'rate_usd' => (float) $usd, // USD per 1 BTC
                    'source'   => 'coinbase',
                    'as_of'    => now(),
                ];
            } catch (\Throwable $e) {
                return null; // fail closed; UI can fall back to manual entry
            }
        });
    }

    public static function fresh(): ?array
    {
        try {
            // No cache — always fetch live
            $res = \Illuminate\Support\Facades\Http::timeout(6)
                ->retry(2, 200)
                ->acceptJson()
                ->get('https://api.coinbase.com/v2/prices/BTC-USD/spot');

            if (!$res->ok()) return null;

            $amount = (float) data_get($res->json(), 'data.amount');
            if ($amount <= 0) return null;

            return [
                'rate_usd' => $amount,
                'as_of'    => now(),
                'source'   => 'coinbase:spot',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }


    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
