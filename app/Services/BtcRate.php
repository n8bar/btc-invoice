<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $normalized = self::normalize($cached);

        if ($normalized && self::isFresh($normalized)) {
            return $normalized;
        }

        return static::refreshCache();
    }

    public static function fresh(): ?array
    {
        try {
            // No cache — always fetch live
            $res = Http::timeout(6)
                ->retry(2, 200)
                ->acceptJson()
            ->get('https://api.coinbase.com/v2/prices/BTC-USD/spot');

            if (!$res->ok()) {
                Log::warning('btc_rate.fetch_failed', ['status' => $res->status(), 'body' => $res->body()]);
                return null;
            }

            $amount = (float) data_get($res->json(), 'data.amount');
            if ($amount <= 0) return null;

            return [
                'rate_usd' => $amount,
                'as_of'    => now(),
                'source'   => 'coinbase:spot',
            ];
        } catch (\Throwable $e) {
            Log::warning('btc_rate.fetch_error', ['error' => $e->getMessage()]);
            return null;
        }
    }


    public static function refreshCache(): ?array
    {
        $fresh = static::fresh();

        if (!$fresh) {
            return null;
        }

        $normalized = static::normalize($fresh);

        Cache::put(self::CACHE_KEY, $normalized, self::TTL);
        Log::info('btc_rate.cached', [
            'rate_usd' => $normalized['rate_usd'] ?? null,
            'source' => $normalized['source'] ?? 'unknown',
        ]);

        return $normalized;
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

    private static function isFresh(array $rate): bool
    {
        if (empty($rate['as_of']) || !$rate['as_of'] instanceof Carbon) {
            return false;
        }

        return $rate['as_of']->diffInSeconds(now()) <= self::TTL;
    }
}
