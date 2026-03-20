<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupportDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $owners = User::query()
            ->whereNotNull('support_access_granted_at')
            ->where('support_access_expires_at', '>', now())
            ->withCount(['clients', 'invoices'])
            ->orderBy('support_access_expires_at')
            ->paginate(15)
            ->withQueryString();

        return view('support.dashboard', [
            'owners' => $owners,
        ]);
    }
}
