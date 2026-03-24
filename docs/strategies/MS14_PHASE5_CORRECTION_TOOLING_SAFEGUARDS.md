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

### Browser QA
- [ ] Ignore, restore, and reattribute recover truthful visible invoice state.
- [ ] Ignore and reattribute settlement math update immediately on the correct invoice or invoices.
- [ ] Owner history remains understandable after reattribution while public/print reflects only the active accounting.
- [ ] Reattribution does not allow untruthful queued payment-triggered mail to send.
- [ ] Stale-address wrong-invoice cases do not trigger unsupported-wallet UI by themselves.
- [ ] Manual adjustment rows expose no correction controls through the payment-correction flow.
- [ ] Force delete shows clear resolution guidance, including source-invoice guidance for active reattributions, without auto-converting anything for the owner.
