# MS14 Phase 3 Strategy - Unsupported Configuration Detection + Flagging

Status: Completed.
Parent milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](../milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)

Detect risky wallet reuse, flag the wallet gently but clearly, and snapshot unsupported state only where evidence supports it.

## 3.1 Persist unsupported wallet and invoice state
1. [x] Add wallet-level unsupported-state fields that capture whether the primary wallet is currently flagged plus enough metadata to explain why it was flagged.
2. [x] Add invoice-level unsupported-state fields so newly created invoices can snapshot wallet unsupported state and existing invoices can be flagged individually when direct evidence implicates them.
3. [x] Keep unsupported-state metadata explicit enough to distinguish proactive detection from evidence-triggered detection.
   - Current implementation on 2026-03-18: wallet settings now persist `unsupported_configuration_active` plus `source` / `reason` / `details` / `flagged_at`; new invoices snapshot that state at creation time; replacing the primary wallet key clears the current wallet-level unsupported flag without retroactively changing older invoice snapshots.

## 3.2 Detect unsupported wallet activity proactively
1. [x] Inspect the saved primary wallet key for prior outside receive activity when the owner saves or replaces the wallet.
2. [x] Flag the wallet as unsupported when proactive detection finds prior activity that makes automatic tracking unreliable.
3. [x] Allow the owner to continue after save instead of hard-blocking the wallet flow.
4. [x] Treat outside receive activity as the trigger; spending elsewhere alone is not enough to flag the wallet unsupported.
   - Current implementation on 2026-03-18: wallet save now scans a bounded window of derived external receive addresses through the existing mempool client, ignores receive history on addresses already owned by this same key lineage inside CryptoZing, flags the wallet when an unknown derived receive address shows prior incoming funds, and leaves spend-only history unflagged.

## 3.3 Detect unsupported cases from invoice/payment evidence
1. [x] Flag the wallet as unsupported when watcher or lineage evidence later shows collision-style activity that undermines automatic attribution.
2. [x] Mark only the implicated existing invoice unsupported when the evidence is specific to that invoice.
3. [x] Do not retroactively bulk-mark older invoices unsupported without invoice-specific evidence.
4. [x] Mark every newly created invoice unsupported while the wallet remains flagged unsupported.
   - Current implementation on 2026-03-18: payment sync now treats a paid shared-address collision as invoice/payment evidence, marks each invoice using that paid address unsupported, flags the current wallet only when the invoice lineage still matches the owner's currently saved primary key, and leaves unrelated older invoices untouched.

## 3.4 Surface unsupported state in app chrome and wallet flows
1. [x] Show red attention UI only when the wallet is actually flagged unsupported:
   1. [x] an attention-grabbing label near the user menu
   2. [x] a red dot on the Settings nav item
   3. [x] a red dot on the Wallet settings tab
   4. [x] a red warning near the wallet account key field
2. [x] Keep the warning copy gentle and corrective:
   1. [x] explain that CryptoZing found wallet activity outside its dedicated receive flow
   2. [x] explain that automatic tracking is no longer reliable for this wallet account
   3. [x] direct the owner to connect a fresh dedicated account key
3. [x] Mark invoices created while the wallet is flagged unsupported as unsupported in their own UI/state so that replacing the wallet later does not silently make them look safe.
   - Current implementation on 2026-03-18: wallet settings show a gentle corrective warning block and flagged invoices display unsupported markers in the invoice list and on the invoice detail page. Follow-up Browser QA on 2026-03-18 confirmed the repair path now works end-to-end through the user-menu pill, Settings red dot, Wallet red dot, and wallet warning block.

## 3.5 Verify Phase 3
Automated / command verification:
1. [x] Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 3 work.
   - Current result on 2026-03-19: `222 passed`.
2. [x] Add or expand automated coverage for:
   1. [x] proactive unsupported-state detection when a saved wallet shows prior outside receive activity
   2. [x] evidence-triggered wallet flagging from invoice/payment collisions
   3. [x] new invoices inheriting unsupported state while the wallet is flagged
   4. [x] existing invoices remaining unflagged unless invoice-specific evidence marks them
   5. [x] unsupported-state UI indicators appearing only when the wallet is actually flagged
   - Current result on 2026-03-19: automated coverage now also includes the flagged `/invoices/create` warning plus `Create Unsupported Invoice` CTA.

Browser QA:
3. [x] Save a wallet key that triggers proactive unsupported-state detection and confirm the owner can continue while the app shows the red warning state only in the intended places.
   - Current implementation on 2026-03-19: wallet save remains non-blocking, the repair path works through the user-menu unsupported pill plus visible red dots on Settings and Wallet, the wallet warning block renders as intended, and `/invoices/create` now shows its own inline unsupported warning plus a `Create Unsupported Invoice` primary CTA while the wallet is flagged.
4. [x] Confirm the wallet warning language stays gentle and points the owner toward connecting a new dedicated account key.
5. [x] Create a new invoice while the wallet is flagged and confirm the invoice is marked unsupported at creation time.
6. [x] Confirm an older invoice is not retroactively marked unsupported unless evidence for that specific invoice triggers it.
