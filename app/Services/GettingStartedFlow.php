<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GettingStartedFlow
{
    public const STEP_WALLET = 'wallet';
    public const STEP_INVOICE = 'invoice';
    public const STEP_DELIVER = 'deliver';

    /**
     * @return list<string>
     */
    public function stepOrder(): array
    {
        return [
            self::STEP_WALLET,
            self::STEP_INVOICE,
            self::STEP_DELIVER,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function stepDefinitions(): array
    {
        return [
            self::STEP_WALLET => [
                'label' => 'Connect wallet',
                'title' => 'Connect your wallet',
                'body' => 'Add your wallet account key so CryptoZing can generate a payment address for each invoice.',
                'cta_label' => 'Open wallet settings',
                'criteria' => 'Save a valid wallet account key.',
            ],
            self::STEP_INVOICE => [
                'label' => 'Create invoice',
                'title' => 'Create your first invoice',
                'body' => 'Create an invoice to continue getting started.',
                'cta_label' => 'Create invoice',
                'criteria' => 'Create at least one invoice.',
            ],
            self::STEP_DELIVER => [
                'label' => 'Share + deliver',
                'title' => 'Share and send your invoice',
                'body' => 'Enable the public link, then send the invoice email.',
                'cta_label' => 'Open invoice',
                'criteria' => 'Enable a public link and log a delivery attempt.',
            ],
        ];
    }

    public function isValidStep(string $step): bool
    {
        return in_array($step, $this->stepOrder(), true);
    }

    public function stepIndex(string $step): int
    {
        $index = array_search($step, $this->stepOrder(), true);

        if ($index === false) {
            throw new \InvalidArgumentException("Invalid getting-started step [{$step}]");
        }

        return $index;
    }

    /**
     * @return array{
     *   steps: array<string, array<string, mixed>>,
     *   first_incomplete_step: string|null,
     *   is_complete: bool
     * }
     */
    public function snapshot(User $user): array
    {
        $walletComplete = $user->walletSetting()->exists();
        $invoiceComplete = $user->invoices()->exists();
        $deliverComplete = Invoice::query()
            ->ownedBy($user)
            ->where('public_enabled', true)
            ->whereHas('deliveries')
            ->exists();

        $completionMap = [
            self::STEP_WALLET => $walletComplete,
            self::STEP_INVOICE => $invoiceComplete,
            self::STEP_DELIVER => $deliverComplete,
        ];

        $steps = [];
        $firstIncomplete = null;
        $definitions = $this->stepDefinitions();

        foreach ($this->stepOrder() as $index => $step) {
            $complete = $completionMap[$step];

            if (! $complete && $firstIncomplete === null) {
                $firstIncomplete = $step;
            }

            $steps[$step] = $definitions[$step] + [
                'key' => $step,
                'position' => $index + 1,
                'complete' => $complete,
            ];
        }

        return [
            'steps' => $steps,
            'first_incomplete_step' => $firstIncomplete,
            'is_complete' => $firstIncomplete === null,
        ];
    }

    public function resolveDeliverInvoice(User $user, mixed $requestedInvoiceId = null): ?Invoice
    {
        $query = $this->deliverInvoiceQuery($user);

        if ($requestedInvoiceId !== null) {
            $invoiceId = filter_var($requestedInvoiceId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($invoiceId !== false) {
                $override = (clone $query)
                    ->whereKey($invoiceId)
                    ->first();

                if ($override) {
                    return $override;
                }
            }
        }

        return (clone $query)->latest('id')->first();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function deliverInvoiceOptions(User $user): Collection
    {
        return $this->deliverInvoiceQuery($user)
            ->with('client:id,name')
            ->latest('id')
            ->get(['id', 'client_id', 'number', 'status']);
    }

    public function markCompleted(User $user): void
    {
        $timestamp = now();

        $user->forceFill([
            'getting_started_completed_at' => $timestamp,
            'getting_started_dismissed' => false,
        ])->save();

        $user->setAttribute('getting_started_completed_at', $timestamp);
        $user->setAttribute('getting_started_dismissed', false);
    }

    public function dismiss(User $user): void
    {
        $timestamp = now();

        $user->forceFill([
            'getting_started_completed_at' => $timestamp,
            'getting_started_dismissed' => true,
        ])->save();

        $user->setAttribute('getting_started_completed_at', $timestamp);
        $user->setAttribute('getting_started_dismissed', true);
    }

    public function reopen(User $user): void
    {
        $user->forceFill([
            'getting_started_completed_at' => null,
            'getting_started_dismissed' => false,
        ])->save();

        $user->setAttribute('getting_started_completed_at', null);
        $user->setAttribute('getting_started_dismissed', false);
    }

    public function doneStatusMessage(User $user): string
    {
        return $user->gettingStartedWasDismissed()
            ? 'Getting started hidden.'
            : 'Getting started complete.';
    }

    /**
     * @return array<string, mixed>
     */
    public function progressStrip(User $user, string $currentStep, ?Invoice $deliverInvoice = null): array
    {
        if (! $this->isValidStep($currentStep)) {
            throw new \InvalidArgumentException("Invalid getting-started step [{$currentStep}]");
        }

        $snapshot = $this->snapshot($user);
        $steps = $snapshot['steps'];
        $current = $steps[$currentStep];

        $backRouteParams = ['step' => $currentStep];
        if ($currentStep === self::STEP_DELIVER && $deliverInvoice) {
            $backRouteParams['invoice'] = $deliverInvoice->id;
        }

        return [
            'steps' => array_values($steps),
            'current_step_key' => $currentStep,
            'current_step' => $current,
            'step_count' => count($steps),
            'earliest_incomplete_step' => $snapshot['first_incomplete_step'],
            'back_url' => route('getting-started.step', $backRouteParams),
        ];
    }

    private function deliverInvoiceQuery(User $user): Builder
    {
        return Invoice::query()
            ->ownedBy($user)
            ->where('status', 'draft');
    }
}
