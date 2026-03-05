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

    public function welcome(Request $request, GettingStartedFlow $flow): View|RedirectResponse
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

        if (! $this->shouldShowWelcome($snapshot)) {
            return redirect()->route('getting-started.start');
        }

        return view('getting-started.welcome', [
            'stepCount' => count($snapshot['steps']),
            'startUrl' => route('getting-started.step', ['step' => GettingStartedFlow::STEP_WALLET]),
            'backUrl' => route('dashboard'),
        ]);
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
        $deliverInvoiceOptions = collect();
        if ($step === GettingStartedFlow::STEP_DELIVER) {
            $deliverInvoice = $flow->resolveDeliverInvoice($user, $request->query('invoice'));
            $deliverInvoiceOptions = $flow->deliverInvoiceOptions($user);
        }

        $steps = $snapshot['steps'];
        $currentStep = $steps[$step];

        $actionUrl = match ($step) {
            GettingStartedFlow::STEP_WALLET => route('wallet.settings.edit', ['getting_started' => 1]),
            GettingStartedFlow::STEP_INVOICE => route('invoices.create', ['getting_started' => 1]),
            GettingStartedFlow::STEP_DELIVER => $deliverInvoice
                ? route('invoices.show', [
                    'invoice' => $deliverInvoice,
                    'getting_started' => 1,
                ])
                : route('invoices.create', ['getting_started' => 1]),
        };
        $actionLabel = $step === GettingStartedFlow::STEP_DELIVER && ! $deliverInvoice
            ? 'Create new draft invoice'
            : $currentStep['cta_label'];
        $showInvoiceDraftRequiredWarning = false;
        if ($step === GettingStartedFlow::STEP_INVOICE && !($snapshot['is_replay'] ?? false)) {
            $hasAnyInvoice = $user->invoices()->exists();
            $hasDraftInvoice = $user->invoices()->where('status', 'draft')->exists();
            $showInvoiceDraftRequiredWarning = $hasAnyInvoice && !$hasDraftInvoice;

            if ($showInvoiceDraftRequiredWarning) {
                $actionLabel = 'Create new draft invoice';
            }
        }

        $stepUrls = [
            GettingStartedFlow::STEP_WALLET => route('getting-started.step', ['step' => GettingStartedFlow::STEP_WALLET]),
            GettingStartedFlow::STEP_INVOICE => route('getting-started.step', ['step' => GettingStartedFlow::STEP_INVOICE]),
            GettingStartedFlow::STEP_DELIVER => $deliverInvoice
                ? route('getting-started.step', [
                    'step' => GettingStartedFlow::STEP_DELIVER,
                    'invoice' => $deliverInvoice->id,
                ])
                : route('getting-started.step', ['step' => GettingStartedFlow::STEP_DELIVER]),
        ];
        $earliestIncompleteStepUrl = $earliestIncomplete !== null
            ? ($stepUrls[$earliestIncomplete] ?? null)
            : null;
        $suppressRequiredStepNotice = $step === GettingStartedFlow::STEP_INVOICE
            && $earliestIncomplete === GettingStartedFlow::STEP_DELIVER;
        $showRequiredStepNotice = $currentStep['key'] !== $earliestIncomplete && ! $suppressRequiredStepNotice;

        $backUrl = route('dashboard');

        return view('getting-started.step', [
            'steps' => array_values($steps),
            'currentStep' => $currentStep,
            'currentStepKey' => $step,
            'currentStepNumber' => $currentStep['position'],
            'stepCount' => count($steps),
            'actionUrl' => $actionUrl,
            'actionLabel' => $actionLabel,
            'deliverInvoice' => $deliverInvoice,
            'deliverInvoiceOptions' => $deliverInvoiceOptions,
            'earliestIncompleteStep' => $earliestIncomplete,
            'earliestIncompleteStepUrl' => $earliestIncompleteStepUrl,
            'showRequiredStepNotice' => $showRequiredStepNotice,
            'showInvoiceDraftRequiredWarning' => $showInvoiceDraftRequiredWarning,
            'stepUrls' => $stepUrls,
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

    /**
     * @param  array{steps: array<string, array<string, mixed>>, first_incomplete_step: string|null, is_complete: bool}  $snapshot
     */
    private function shouldShowWelcome(array $snapshot): bool
    {
        return collect($snapshot['steps'])->every(static fn (array $step): bool => $step['complete'] === false);
    }
}
