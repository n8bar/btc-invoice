<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $issuers = User::query()
            ->whereNotNull('support_access_granted_at')
            ->where('support_access_expires_at', '>', now())
            ->withCount(['clients', 'invoices'])
            ->orderBy('support_access_expires_at')
            ->paginate(15)
            ->withQueryString();

        return view('support.dashboard', [
            'issuers' => $issuers,
            'monitoring' => $this->monitoringPanel(),
        ]);
    }

    private function monitoringPanel(): array
    {
        $queueDepth = DB::table('invoice_deliveries')
            ->whereIn('status', ['queued', 'sending'])
            ->count();

        $recentFailures = DB::table('invoice_deliveries')
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subHours(24))
            ->orderByDesc('updated_at')
            ->get(['type', 'recipient', 'error_message', 'updated_at']);

        $lastPaymentAt = DB::table('invoice_payments')
            ->where('is_adjustment', false)
            ->max(DB::raw('COALESCE(detected_at, created_at)'));

        $staleMinutes = config('support.watcher_stale_minutes', 60);
        $watcherStale = $lastPaymentAt !== null
            && now()->diffInMinutes($lastPaymentAt, false) < -$staleMinutes;

        return [
            'queue_depth'     => $queueDepth,
            'recent_failures' => $recentFailures,
            'last_payment_at' => $lastPaymentAt,
            'watcher_stale'   => $watcherStale,
            'stale_minutes'   => $staleMinutes,
        ];
    }
}
