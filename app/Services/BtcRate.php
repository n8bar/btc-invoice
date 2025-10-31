<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
class BtcRate
{
    const CACHE_KEY = 'btc_rate:spot';
    const TTL = 3600; // seconds

    /**
     * Returns ['rate_usd' => float, 'source' => 'coinbase', 'as_of' => Carbon] or null.
     */
    public static function current(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached) {
            return self::normalize($cached);
        }

        $fresh = self::fresh();
        if (!$fresh) {
            return null;
        }

        $normalized = self::normalize($fresh);
        Cache::put(self::CACHE_KEY, $normalized, self::TTL);

        return $normalized;
    }

    public static function fresh(): ?array
    {
        try {
            // No cache — always fetch live
            $res = Http::timeout(6)
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


    public static function refreshCache(): ?array
    {
        // same key current() uses
        $key = 'btc_rate:spot';

        Cache::forget($key);

        $live = static::fresh();
        if (!$live) return null;

        $payload = [
            'rate_usd' => $live['rate_usd'],
            'as_of'    => $live['as_of'] instanceof Carbon
                ? $live['as_of']
                : Carbon::parse($live['as_of']),
            'source'   => $live['source'] ?? 'coinbase:spot',
        ];

        Cache::put($key, $payload, now()->addHour());
        return $payload;
    }



    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function normalize(?array $rate): ?array
    {
        if (!$rate) {
            return null;
        }

        if (!empty($rate['as_of']) && !$rate['as_of'] instanceof Carbon) {
            $rate['as_of'] = Carbon::parse($rate['as_of']);
        }

        return $rate;
    }
}
