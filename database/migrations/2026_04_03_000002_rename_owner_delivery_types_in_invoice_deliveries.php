<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'owner_paid_notice'            => 'issuer_paid_notice',
            'payment_acknowledgment_owner' => 'payment_acknowledgment_issuer',
            'past_due_owner'               => 'past_due_issuer',
            'owner_overpay_alert'          => 'issuer_overpay_alert',
            'owner_underpay_alert'         => 'issuer_underpay_alert',
            'owner_partial_warning'        => 'issuer_partial_warning',
        ];

        foreach ($renames as $old => $new) {
            DB::table('invoice_deliveries')
                ->where('type', $old)
                ->update(['type' => $new]);
        }
    }

    public function down(): void
    {
        $renames = [
            'issuer_paid_notice'            => 'owner_paid_notice',
            'payment_acknowledgment_issuer' => 'payment_acknowledgment_owner',
            'past_due_issuer'               => 'past_due_owner',
            'issuer_overpay_alert'          => 'owner_overpay_alert',
            'issuer_underpay_alert'         => 'owner_underpay_alert',
            'issuer_partial_warning'        => 'owner_partial_warning',
        ];

        foreach ($renames as $old => $new) {
            DB::table('invoice_deliveries')
                ->where('type', $old)
                ->update(['type' => $new]);
        }
    }
};
