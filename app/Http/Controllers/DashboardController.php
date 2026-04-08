<?php

namespace App\Http\Controllers;

use App\Services\DashboardSnapshot;
use App\Services\GettingStartedFlow;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSnapshot $snapshot, GettingStartedFlow $flow)
    {
        $user = $request->user();

        if ($user->isSupportAgent()) {
            return redirect()->route('support.dashboard');
        }

        $data = $snapshot->forUser($user);

        $receiptStepPending = ! $user->gettingStartedWasDismissed() && $flow->receiptStepPending($user);
        $showGettingStartedPrompt = $user->gettingStartedNeedsAutoShow() || $receiptStepPending;

        return view('dashboard', [
            'snapshot' => $data,
            'hasClients' => $user->clients()->exists(),
            'showGettingStartedPrompt' => $showGettingStartedPrompt,
            'gettingStartedReceiptStepPending' => $receiptStepPending && $user->gettingStartedIsDone(),
            'gettingStartedUrl' => route('getting-started.start'),
        ]);
    }
}
