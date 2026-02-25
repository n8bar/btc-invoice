<?php

namespace App\Http\Controllers;

use App\Services\GettingStartedFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GettingStartedController extends Controller
{
    public function start(Request $request, GettingStartedFlow $flow): RedirectResponse
    {
        $user = $request->user();

        if ($user->gettingStartedIsDone()) {
            return redirect()
                ->route('dashboard')
                ->with('status', $flow->doneStatusMessage($user));
        }

        $snapshot = $flow->snapshot($user);

        if ($snapshot['is_complete']) {
            $flow->markCompleted($user);

            return redirect()
                ->route('dashboard')
                ->with('status', 'Getting started complete.');
        }

        $targetStep = $snapshot['first_incomplete_step'];
        $routeParams = ['step' => $targetStep];

        if ($targetStep === GettingStartedFlow::STEP_DELIVER) {
            $invoice = $flow->resolveDeliverInvoice($user, $request->query('invoice'));
            if ($invoice) {
                $routeParams['invoice'] = $invoice->id;
            }
        }

        return redirect()->route('getting-started.step', $routeParams);
    }

    public function step(Request $request, GettingStartedFlow $flow, string $step): View|RedirectResponse
    {
        if (! $flow->isValidStep($step)) {
            abort(404);
        }

        $user = $request->user();

        if ($user->gettingStartedIsDone()) {
            return redirect()
                ->route('dashboard')
                ->with('status', $flow->doneStatusMessage($user));
        }

        $snapshot = $flow->snapshot($user);

        if ($snapshot['is_complete']) {
            $flow->markCompleted($user);

            return redirect()
                ->route('dashboard')
                ->with('status', 'Getting started complete.');
        }

        $earliestIncomplete = $snapshot['first_incomplete_step'];
        if (
            $earliestIncomplete !== null
            && $flow->stepIndex($step) > $flow->stepIndex($earliestIncomplete)
        ) {
            $routeParams = ['step' => $earliestIncomplete];

            if ($earliestIncomplete === GettingStartedFlow::STEP_DELIVER) {
                $invoice = $flow->resolveDeliverInvoice($user, $request->query('invoice'));
                if ($invoice) {
                    $routeParams['invoice'] = $invoice->id;
                }
            }

            return redirect()->route('getting-started.step', $routeParams);
        }

        $deliverInvoice = null;
        if ($step === GettingStartedFlow::STEP_DELIVER) {
            $deliverInvoice = $flow->resolveDeliverInvoice($user, $request->query('invoice'));

            if (! $deliverInvoice) {
                return redirect()->route('getting-started.step', ['step' => GettingStartedFlow::STEP_INVOICE]);
            }
        }

        $steps = $snapshot['steps'];
        $currentStep = $steps[$step];

        $actionUrl = match ($step) {
            GettingStartedFlow::STEP_WALLET => route('wallet.settings.edit', ['getting_started' => 1]),
            GettingStartedFlow::STEP_INVOICE => route('invoices.create', ['getting_started' => 1]),
            GettingStartedFlow::STEP_DELIVER => route('invoices.show', [
                'invoice' => $deliverInvoice,
                'getting_started' => 1,
            ]),
        };

        $backUrl = route('dashboard');

        return view('getting-started.step', [
            'steps' => array_values($steps),
            'currentStep' => $currentStep,
            'currentStepKey' => $step,
            'currentStepNumber' => $currentStep['position'],
            'stepCount' => count($steps),
            'actionUrl' => $actionUrl,
            'deliverInvoice' => $deliverInvoice,
            'earliestIncompleteStep' => $earliestIncomplete,
            'backUrl' => $backUrl,
        ]);
    }

    public function dismiss(Request $request, GettingStartedFlow $flow): RedirectResponse
    {
        $flow->dismiss($request->user());

        return redirect()
            ->route('dashboard')
            ->with('status', 'Getting started hidden.');
    }

    public function reopen(Request $request, GettingStartedFlow $flow): RedirectResponse
    {
        $flow->reopen($request->user());

        return redirect()->route('getting-started.start');
    }
}
