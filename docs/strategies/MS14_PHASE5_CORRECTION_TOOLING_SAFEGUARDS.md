# MS14 Phase 5 Strategy - Correction Tooling + Safeguards

Status: Active.
Parent milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](../milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)
Canonical requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

This strategy owns the execution order for the remaining Phase 5 work. Use the milestone doc for phase rollup and the spec for behavior and invariants.

- Ignore/restore correction metadata lives on `invoice_payments`.
- Owner-only ignore/restore actions ship from the invoice payment-history table with inline confirmation.
- Ignored rows stay visible in owner/support audit views while public/print/dashboard math excludes them.
- Watcher sync preserves ignored rows, and queued payment-triggered deliveries made untruthful by ignore/restore are skipped.
- Baseline Phase 5 coverage for ignore/restore shipped, and `./vendor/bin/sail artisan test` passed on 2026-03-19 (`246 passed`).

## 1. Add reattribution state to `invoice_payments`
1. [ ] Add `accounting_invoice_id`, `reattributed_at`, `reattributed_by_user_id`, and `reattribute_reason`.
2. [ ] Keep `invoice_id` as immutable source provenance and use `accounting_invoice_id` as the sole active accounting destination.

## 2. Implement reattribution ledger behavior and recomputation
1. [ ] Recompute both source and destination invoices immediately after reattribution.
2. [ ] Re-run the same post-payment truthfulness checks so queued payment-triggered deliveries are suppressed when reattribution makes them untruthful.
3. [ ] Keep stale-address wrong-invoice cases as correction work, not unsupported-wallet evidence by default.

## 3. Ship the owner reattribute flow
1. [ ] Add owner-only route/controller/form handling for `PATCH /invoices/{invoice}/payments/{payment}/reattribute`.
2. [ ] Enforce same-owner destination validation and keep manual adjustment rows out of the correction flow.
3. [ ] Preserve selected destination and typed reason on validation failure.

## 4. Render reattribution truthfully across surfaces
1. [ ] Source owner history shows reattributed-out rows without counting them.
2. [ ] Destination owner history shows reattributed-in rows as active credit.
3. [ ] Public/print show the payment only on the destination invoice, without source provenance, related-invoice links, or reattribution labels.
4. [ ] Owner/support related-invoice links stay off public/print surfaces.

## 5. Finish destructive-delete safeguards
1. [ ] Add one shared preflight guard for force delete.
2. [ ] Choose and implement the persistence-layer hard-delete backstop.
3. [ ] Keep detected payments, ignored rows, manual adjustments, and active reattribution source/destination cases blocking force delete until intentionally resolved.

## 6. Add the remaining automated coverage
1. [ ] Reattribution recomputes source and destination invoices truthfully.
2. [ ] Same-owner validation, immutable source provenance, and current-state reattribution metadata behave as specified.
3. [ ] Reattribution audit logs, surface behavior, queued-delivery suppression, and stale-address unsupported-wallet boundaries hold.
4. [ ] Force delete blocking covers every unresolved bookkeeping blocker class with both app-layer guidance and the persistence-layer backstop.

## 7. Run the closing Browser QA and final Phase 5 test pass
1. [ ] Ignore, restore, and reattribute all recover truthful visible invoice state.
2. [ ] Source/destination owner history and public/print visibility rules match the spec.
3. [ ] Manual adjustment rows expose no correction controls.
4. [ ] Force delete guidance stays clear and never auto-converts anything.
5. [ ] Finish with `./vendor/bin/sail artisan test`.

## Verify Phase 5
### Automated
- [ ] Reattribution recomputes source and destination invoices truthfully.
- [ ] Same-owner destination validation holds.
- [ ] `invoice_payments` keeps immutable source provenance while only the active accounting destination and current reattribution state change.
- [ ] Reattribution audit logs and metadata persist.
- [ ] Reattribution suppresses queued payment-triggered deliveries that would become untruthful.
- [ ] Stale-address wrong-invoice cases stay outside unsupported-wallet evidence without separate facts.
- [ ] Source and destination histories preserve the correct reattributed-out / reattributed-in presentation.
- [ ] Owner/support related-invoice links stay off public/print surfaces.
- [ ] Force delete stays blocked across all unresolved bookkeeping blocker classes.
- [ ] `./vendor/bin/sail artisan test`

### BQA Prep
- Agent-run only. Complete this prep before handing the browser pass to a human.

1. [ ] Start the local stack with `./vendor/bin/sail up -d` and leave the `scheduler` service running.
2. [ ] Confirm the controlled MS14 baseline from [`docs/strategies/MS14_PHASE1_BASELINE_RESEED.md`](MS14_PHASE1_BASELINE_RESEED.md) is present. If invoices `50` through `58` are missing, stop and rebuild that baseline first.
3. [ ] Leave queue jobs undrained while testing payment-correction suppression. This stack has a scheduler but no always-on queue worker, so use the invoice delivery log as the source of truth for `queued` -> `skipped`.
4. [ ] Prepare Scenario A for ignore/paid rollback:
   1. [ ] Create a sent invoice with a client email and enable its public link.
   2. [ ] Turn on `Settings > Notifications > Auto email paid receipts`.
   3. [ ] Send one `testnet4` payment that fully pays the invoice from local-only funding material stored outside the repo boundary (for example under `.cybercreek/`).
   4. [ ] Run `./vendor/bin/sail artisan wallet:watch-payments --invoice={invoiceId}` until the invoice becomes `paid`.
   5. [ ] Open the invoice delivery log and verify `receipt` and `owner_paid_notice` rows are `Queued`.
5. [ ] Prepare Scenario B for restore/underpay cleanup:
   1. [ ] Create a second sent invoice with the same owner and a client email.
   2. [ ] Send three confirmed `testnet4` payments so the invoice reaches `paid`.
   3. [ ] Run `./vendor/bin/sail artisan wallet:watch-payments --invoice={invoiceId}` until the invoice shows `paid`.
   4. [ ] Prepare one payment row in the ignored state so the invoice falls back to `partial` while at least two active payments remain.
   5. [ ] Reopen the invoice delivery log and verify `client_underpay_alert`, `owner_underpay_alert`, `client_partial_warning`, and `owner_partial_warning` rows are `Queued`.
6. [ ] Prepare Scenario C for same-owner reattribution:
   1. [ ] Create source invoice A and destination invoice B for the same owner.
   2. [ ] Enable the public link on both invoices.
   3. [ ] Send one confirmed `testnet4` payment to source invoice A and run `./vendor/bin/sail artisan wallet:watch-payments --invoice={sourceInvoiceId}` until the payment row appears.
   4. [ ] After destination invoice B exists, send one additional `testnet4` payment to source invoice A's old address and rerun `./vendor/bin/sail artisan wallet:watch-payments --invoice={sourceInvoiceId}` until the later payment row appears.
7. [ ] Leave a handoff note for Browser QA with:
   1. [ ] Scenario A invoice ID and the payment row to ignore.
   2. [ ] Scenario B invoice ID and the ignored payment row to restore.
   3. [ ] Scenario C source invoice ID, destination invoice ID, and the payment row to reattribute.
   4. [ ] Any delivery-log rows the human should expect to see as `Queued` before Browser QA starts.

### Browser QA
1. [ ] Log in as `antonina12@nospam.site` with password `password`.
2. [ ] Ignore a paid payment and verify paid-state rollback:
   1. [ ] Open Scenario A's invoice and click `Ignore` on the detected payment.
   2. [ ] Enter a reason and submit.
   3. [ ] Verify the invoice leaves `paid`, settlement math reopens truthfully, the payment row remains visible in owner history as ignored, and the `receipt` / `owner_paid_notice` rows change from `Queued` to `Skipped`.
   4. [ ] Open print/public surfaces and verify the ignored payment does not appear in payment history or totals.
3. [ ] Restore an ignored payment and verify alert suppression cleanup:
   1. [ ] Open Scenario B's invoice and click `Restore` on the ignored payment.
   2. [ ] Verify the invoice returns to the truthful paid state, ignore metadata clears, and the queued `client_underpay_alert`, `owner_underpay_alert`, `client_partial_warning`, and `owner_partial_warning` rows change to `Skipped`.
4. [ ] Reattribute a payment to a same-owner destination invoice:
   1. [ ] On Scenario C source invoice A, click `Reattribute`, choose destination invoice B, enter a reason, and submit.
   2. [ ] Verify source owner history shows the payment as reattributed out and no longer counting there.
   3. [ ] Verify destination owner history shows the same payment as reattributed in and counting there.
5. [ ] Verify public and print surfaces after reattribution:
   1. [ ] Open source invoice A public/print surfaces and verify the reattributed payment is absent.
   2. [ ] Open destination invoice B public/print surfaces and verify the payment is present and counted there.
   3. [ ] Verify neither public/print surface exposes source provenance, related-invoice links, or reattribution labels.
6. [ ] Verify the stale-address wrong-invoice boundary:
   1. [ ] Use the later payment row created in Scenario C.
   2. [ ] Verify the later payment appears as a normal correction candidate on source invoice A.
   3. [ ] Verify unsupported-wallet UI does not appear solely because of that later payment.
   4. [ ] Reattribute that later payment to destination invoice B and re-check owner/public behavior.
7. [ ] Verify manual-adjustment guardrails:
   1. [ ] Create a manual adjustment row through the existing adjustment flow on any owned invoice.
   2. [ ] Verify that row shows no `Ignore`, `Restore`, or `Reattribute` controls.
8. [ ] Verify force-delete guidance:
   1. [ ] Attempt force delete on an invoice that still has one unresolved blocker class: detected payment row, ignored row, manual adjustment, or active reattribution.
   2. [ ] Verify force delete is blocked, the blocker is named clearly, source-invoice guidance appears for active reattributions, and the flow offers no one-click auto-conversion or cleanup.
