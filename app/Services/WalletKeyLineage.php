<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\WalletKeyCursor;
use App\Models\WalletSetting;
use Illuminate\Support\Facades\DB;

class WalletKeyLineage
{
    public function __construct(private HdWallet $wallet)
    {
    }

    public function normalizeXpub(string $xpub): string
    {
        return preg_replace('/\s+/', '', trim($xpub)) ?? '';
    }

    public function normalizeNetwork(string $network): string
    {
        return strtolower(trim($network));
    }

    public function fingerprint(string $network, string $xpub): string
    {
        return hash('sha256', $this->normalizeNetwork($network) . '|' . $this->normalizeXpub($xpub));
    }

    public function previewCursor(WalletSetting $wallet): array
    {
        $network = $this->normalizeNetwork((string) $wallet->network);
        $fingerprint = $this->fingerprint($network, (string) $wallet->bip84_xpub);

        $cursor = WalletKeyCursor::query()
            ->where('user_id', $wallet->user_id)
            ->where('network', $network)
            ->where('key_fingerprint', $fingerprint)
            ->first();

        return [
            'wallet_network' => $network,
            'wallet_key_fingerprint' => $fingerprint,
            'next_derivation_index' => max((int) ($cursor?->next_derivation_index ?? 0), $this->safetyFloor($wallet, $fingerprint, $network)),
        ];
    }

    public function previewNextAssignment(WalletSetting $wallet): array
    {
        $preview = $this->previewCursor($wallet);

        return $this->deriveInvoiceLineage($wallet, (int) $preview['next_derivation_index']);
    }

    public function syncWalletCursor(WalletSetting $wallet): WalletKeyCursor
    {
        return DB::transaction(function () use ($wallet) {
            [$cursor] = $this->lockedCursorAndIndex($wallet);

            return $cursor->fresh();
        });
    }

    public function deriveInvoiceLineage(WalletSetting $wallet, int $index): array
    {
        $network = $this->normalizeNetwork((string) $wallet->network);
        $fingerprint = $this->fingerprint($network, (string) $wallet->bip84_xpub);

        return [
            'payment_address' => $this->wallet->deriveAddress((string) $wallet->bip84_xpub, $index, $network),
            'derivation_index' => $index,
            'wallet_key_fingerprint' => $fingerprint,
            'wallet_network' => $network,
        ];
    }

    public function withNextAssignment(WalletSetting $wallet, callable $callback): mixed
    {
        return $this->withPreparedAssignment($wallet, null, $callback);
    }

    public function withPreparedAssignment(WalletSetting $wallet, ?array $preparedLineage, callable $callback): mixed
    {
        return DB::transaction(function () use ($wallet, $preparedLineage, $callback) {
            [$cursor, $index] = $this->lockedCursorAndIndex($wallet);
            $lineage = $this->preparedLineageIsCurrent($wallet, $preparedLineage, $index)
                ? $preparedLineage
                : $this->deriveInvoiceLineage($wallet, $index);
            $result = $callback($lineage);

            $cursor->next_derivation_index = $index + 1;
            $cursor->last_seen_at = now();
            $cursor->save();

            return $result;
        });
    }

    private function preparedLineageIsCurrent(WalletSetting $wallet, ?array $preparedLineage, int $index): bool
    {
        if ($preparedLineage === null) {
            return false;
        }

        $network = $this->normalizeNetwork((string) $wallet->network);

        return (int) ($preparedLineage['derivation_index'] ?? -1) === $index
            && ($preparedLineage['wallet_network'] ?? null) === $network
            && ($preparedLineage['wallet_key_fingerprint'] ?? null) === $this->fingerprint($network, (string) $wallet->bip84_xpub);
    }

    private function lockedCursorAndIndex(WalletSetting $wallet): array
    {
        $network = $this->normalizeNetwork((string) $wallet->network);
        $fingerprint = $this->fingerprint($network, (string) $wallet->bip84_xpub);

        $cursor = WalletKeyCursor::query()
            ->where('user_id', $wallet->user_id)
            ->where('network', $network)
            ->where('key_fingerprint', $fingerprint)
            ->lockForUpdate()
            ->first();

        if (! $cursor) {
            $cursor = WalletKeyCursor::create([
                'user_id' => $wallet->user_id,
                'network' => $network,
                'key_fingerprint' => $fingerprint,
                'next_derivation_index' => 0,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            $cursor = WalletKeyCursor::query()
                ->whereKey($cursor->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $target = max((int) $cursor->next_derivation_index, $this->safetyFloor($wallet, $fingerprint, $network));
        if ((int) $cursor->next_derivation_index !== $target) {
            $cursor->next_derivation_index = $target;
        }

        if (! $cursor->first_seen_at) {
            $cursor->first_seen_at = now();
        }

        $cursor->last_seen_at = now();
        $cursor->save();

        return [$cursor, $target];
    }

    private function safetyFloor(WalletSetting $wallet, string $fingerprint, string $network): int
    {
        $highestAssigned = Invoice::query()
            ->where('user_id', $wallet->user_id)
            ->where('wallet_network', $network)
            ->where('wallet_key_fingerprint', $fingerprint)
            ->whereNotNull('derivation_index')
            ->max('derivation_index');

        $legacyHighest = $this->legacyHighestAssignedIndex($wallet, $network);

        return max(
            $highestAssigned === null ? 0 : ((int) $highestAssigned + 1),
            $legacyHighest === null ? 0 : ($legacyHighest + 1),
        );
    }

    private function legacyHighestAssignedIndex(WalletSetting $wallet, string $network): ?int
    {
        $candidates = Invoice::query()
            ->where('user_id', $wallet->user_id)
            ->whereNotNull('payment_address')
            ->whereNotNull('derivation_index')
            ->whereNull('wallet_key_fingerprint')
            ->orderByDesc('derivation_index')
            ->get(['id', 'payment_address', 'derivation_index']);

        if ($candidates->isEmpty()) {
            return null;
        }

        $derived = [];
        $highest = null;

        foreach ($candidates as $invoice) {
            $index = (int) $invoice->derivation_index;

            if (! array_key_exists($index, $derived)) {
                $derived[$index] = $this->wallet->deriveAddress((string) $wallet->bip84_xpub, $index, $network);
            }

            if ($derived[$index] === $invoice->payment_address) {
                $highest = max($highest ?? $index, $index);
            }
        }

        return $highest;
    }
}
