<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = [
            'db' => false,
            'cache' => false,
        ];

        try {
            DB::select('select 1');
            $checks['db'] = true;
        } catch (\Throwable $e) {
            Log::warning('health.db_failed', ['error' => $e->getMessage()]);
        }

        try {
            $key = 'health:' . uniqid('', true);
            Cache::put($key, 'ok', 5);
            $checks['cache'] = Cache::get($key) === 'ok';
        } catch (\Throwable $e) {
            Log::warning('health.cache_failed', ['error' => $e->getMessage()]);
        }

        $ok = !in_array(false, $checks, true);
        $status = $ok ? 200 : 500;

        return response()->json([
            'ok' => $ok,
            'checks' => $checks,
        ], $status);
    }
}
