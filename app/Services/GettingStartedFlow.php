<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

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
    public function stepDefinitions(bool $replayMode = false): array
    {
        $walletBody = $replayMode
            ? 'Review your current wallet account key and confirm the settings look correct.'
            : 'Add your wallet account key so CryptoZing can generate a payment address for each invoice.';
        $walletCriteria = $replayMode
            ? 'Confirm your wallet settings in this setup run.'
            : 'Save a valid wallet account key.';
        $invoiceBody = $replayMode
            ? 'Create a new invoice to continue setup.'
            : 'Create an invoice to continue getting started.';
        $invoiceCriteria = $replayMode
            ? 'Create at least one new draft invoice in this setup run.'
            : 'Create at least one draft invoice.';
        $deliverCriteria = $replayMode
            ? 'Enable a public link, then send one of your new invoices.'
            : 'Enable a public link, then send the invoice.';

        return [
            self::STEP_WALLET => [
                'label' => 'Connect wallet',
                'title' => 'Connect your wallet',
                'body' => $walletBody,
                'cta_label' => 'Open wallet settings',
                'criteria' => $walletCriteria,
            ],
            self::STEP_INVOICE => [
                'label' => 'Create invoice',
                'title' => 'Create your first invoice',
                'body' => $invoiceBody,
                'cta_label' => 'Create invoice',
                'criteria' => $invoiceCriteria,
            ],
            self::STEP_DELIVER => [
                'label' => 'Share + deliver',
                'title' => 'Share and send your invoice',
                'body' => 'Enable the public link, then send the invoice email.',
                'cta_label' => 'Open invoice',
                'criteria' => $deliverCriteria,
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
        $replayStartedAt = $this->replayStartedAt($user);
        $walletComplete = $this->walletStepComplete($user, $replayStartedAt);
        $invoiceComplete = $this->invoiceStepComplete($user, $replayStartedAt);
        $deliverComplete = $this->deliverStepComplete($user, $replayStartedAt);

        $completionMap = [
            self::STEP_WALLET => $walletComplete,
            self::STEP_INVOICE => $invoiceComplete,
            self::STEP_DELIVER => $deliverComplete,
        ];

        $steps = [];
        $firstIncomplete = null;
        $definitions = $this->stepDefinitions($replayStartedAt !== null);

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
            'is_replay' => $replayStartedAt !== null,
        ];
    }

    public function resolveDeliverInvoice(User $user, mixed $requestedInvoiceId = null): ?Invoice
    {
        $query = $this->deliverInvoiceQuery($user, $this->replayStartedAt($user));

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
        return $this->deliverInvoiceQuery($user, $this->replayStartedAt($user))
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
            'getting_started_replay_started_at' => null,
            'getting_started_replay_wallet_verified_at' => null,
        ])->save();

        $user->setAttribute('getting_started_completed_at', $timestamp);
        $user->setAttribute('getting_started_dismissed', false);
        $user->setAttribute('getting_started_replay_started_at', null);
        $user->setAttribute('getting_started_replay_wallet_verified_at', null);
    }

    public function dismiss(User $user): void
    {
        $timestamp = now();

        $user->forceFill([
            'getting_started_completed_at' => $timestamp,
            'getting_started_dismissed' => true,
            'getting_started_replay_started_at' => null,
            'getting_started_replay_wallet_verified_at' => null,
        ])->save();

        $user->setAttribute('getting_started_completed_at', $timestamp);
        $user->setAttribute('getting_started_dismissed', true);
        $user->setAttribute('getting_started_replay_started_at', null);
        $user->setAttribute('getting_started_replay_wallet_verified_at', null);
    }

    public function reopen(User $user): void
    {
        $replayStartedAt = $this->isCompleteFromExistingData($user) ? now() : null;

        $user->forceFill([
            'getting_started_completed_at' => null,
            'getting_started_dismissed' => false,
            'getting_started_replay_started_at' => $replayStartedAt,
            'getting_started_replay_wallet_verified_at' => null,
        ])->save();

        $user->setAttribute('getting_started_completed_at', null);
        $user->setAttribute('getting_started_dismissed', false);
        $user->setAttribute('getting_started_replay_started_at', $replayStartedAt);
        $user->setAttribute('getting_started_replay_wallet_verified_at', null);
    }

    public function markReplayWalletVerified(User $user): void
    {
        if (! $user->gettingStartedReplayActive()) {
            return;
        }

        if (! $user->walletSetting()->exists()) {
            return;
        }

        $timestamp = now();

        $user->forceFill([
            'getting_started_replay_wallet_verified_at' => $timestamp,
        ])->save();

        $user->setAttribute('getting_started_replay_wallet_verified_at', $timestamp);
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

        [$backUrl, $backLabel] = match ($currentStep) {
            self::STEP_WALLET => [
                route('getting-started.welcome'),
                'Back to welcome',
            ],
            self::STEP_INVOICE => [
                route('getting-started.step', ['step' => self::STEP_WALLET]),
                'Back to connect wallet',
            ],
            self::STEP_DELIVER => [
                route('getting-started.step', ['step' => self::STEP_INVOICE]),
                'Back to create invoice',
            ],
        };

        return [
            'steps' => array_values($steps),
            'current_step_key' => $currentStep,
            'current_step' => $current,
            'step_count' => count($steps),
            'earliest_incomplete_step' => $snapshot['first_incomplete_step'],
            'back_url' => $backUrl,
            'back_label' => $backLabel,
        ];
    }

    private function deliverInvoiceQuery(User $user, ?Carbon $replayStartedAt = null): Builder
    {
        $query = Invoice::query()
            ->ownedBy($user)
            ->where('status', 'draft');

        if ($replayStartedAt !== null) {
            $query->where('created_at', '>', $replayStartedAt);
        }

        return $query;
    }

    private function replayStartedAt(User $user): ?Carbon
    {
        return $user->gettingStartedReplayActive()
            ? $user->getting_started_replay_started_at
            : null;
    }

    private function walletStepComplete(User $user, ?Carbon $replayStartedAt): bool
    {
        if (! $user->walletSetting()->exists()) {
            return false;
        }

        if ($replayStartedAt === null) {
            return true;
        }

        $verifiedAt = $user->getting_started_replay_wallet_verified_at;

        return $verifiedAt !== null && $verifiedAt->greaterThanOrEqualTo($replayStartedAt);
    }

    private function invoiceStepComplete(User $user, ?Carbon $replayStartedAt): bool
    {
        return $this->deliverInvoiceQuery($user, $replayStartedAt)->exists();
    }

    private function deliverStepComplete(User $user, ?Carbon $replayStartedAt): bool
    {
        $query = Invoice::query()
            ->ownedBy($user)
            ->where('public_enabled', true);

        if ($replayStartedAt !== null) {
            $query->where('created_at', '>', $replayStartedAt)
                ->whereHas('deliveries', static function (Builder $deliveries) use ($replayStartedAt): void {
                    $deliveries->where('created_at', '>', $replayStartedAt);
                });
        } else {
            $query->whereHas('deliveries');
        }

        return $query->exists();
    }

    private function isCompleteFromExistingData(User $user): bool
    {
        return $this->walletStepComplete($user, null)
            && $this->invoiceStepComplete($user, null)
            && $this->deliverStepComplete($user, null);
    }
}
