# MS14 Phase 5 Strategy - Correction Tooling + Safeguards

Status: Active.
Parent milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](../milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)
Canonical requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

This strategy owns the execution order for the remaining Phase 5 work. Use the milestone doc for phase rollup and the spec for behavior and invariants.

- [x] Ignore/restore correction metadata lives on `invoice_payments`.
- [x] Owner-only ignore/restore actions ship from the invoice payment-history table with inline confirmation.
- [x] Ignored rows stay visible in owner/support audit views while public/print/dashboard math excludes them.
- [x] Watcher sync preserves ignored rows, and queued payment-triggered deliveries made untruthful by ignore/restore are skipped.
- [x] Baseline Phase 5 coverage for ignore/restore shipped, and `./vendor/bin/sail artisan test` passed on 2026-03-19 (`246 passed`).

## Remaining Sequence
1. [ ] Add reattribution state to `invoice_payments`.
   - Add `accounting_invoice_id`, `reattributed_at`, `reattributed_by_user_id`, and `reattribute_reason`.
   - Keep `invoice_id` as immutable source provenance and use `accounting_invoice_id` as the sole active accounting destination.
2. [ ] Implement reattribution ledger behavior and recomputation.
   - Recompute both source and destination invoices immediately after reattribution.
   - Re-run the same post-payment truthfulness checks so queued payment-triggered deliveries are suppressed when reattribution makes them untruthful.
   - Keep stale-address wrong-invoice cases as correction work, not unsupported-wallet evidence by default.
3. [ ] Ship the owner reattribute flow.
   - Add owner-only route/controller/form handling for `PATCH /invoices/{invoice}/payments/{payment}/reattribute`.
   - Enforce same-owner destination validation and keep manual adjustment rows out of the correction flow.
   - Preserve selected destination and typed reason on validation failure.
4. [ ] Render reattribution truthfully across surfaces.
   - Source owner history shows reattributed-out rows without counting them.
   - Destination owner history shows reattributed-in rows as active credit.
   - Public/print show the payment only on the destination invoice, without source provenance, related-invoice links, or reattribution labels.
   - Owner/support related-invoice links stay off public/print surfaces.
5. [ ] Finish destructive-delete safeguards.
   - Add one shared preflight guard for force delete.
   - Choose and implement the persistence-layer hard-delete backstop.
   - Keep detected payments, ignored rows, manual adjustments, and active reattribution source/destination cases blocking force delete until intentionally resolved.
6. [ ] Add the remaining automated coverage.
   - Reattribution recomputes source and destination invoices truthfully.
   - Same-owner validation, immutable source provenance, and current-state reattribution metadata behave as specified.
   - Reattribution audit logs, surface behavior, queued-delivery suppression, and stale-address unsupported-wallet boundaries hold.
   - Force delete blocking covers every unresolved bookkeeping blocker class with both app-layer guidance and the persistence-layer backstop.
7. [ ] Run the closing Browser QA and final Phase 5 test pass.
   - Ignore, restore, and reattribute all recover truthful visible invoice state.
   - Source/destination owner history and public/print visibility rules match the spec.
   - Manual adjustment rows expose no correction controls.
   - Force delete guidance stays clear and never auto-converts anything.
   - Finish with `./vendor/bin/sail artisan test`.

## Closure Proof
### Automated
- [ ] Reattributing a payment from invoice A to invoice B recomputes both invoices truthfully.
- [ ] Same-owner guardrails and destination validation hold.
- [ ] The canonical payment row keeps immutable source provenance while only the active accounting destination and current-state reattribution metadata change.
- [ ] Reattribution audit logs and metadata persist correctly.
- [ ] Queued payment-triggered deliveries affected by reattribution are skipped or otherwise suppressed when they would become untruthful.
- [ ] Stale-address wrong-invoice cases do not become unsupported-wallet evidence without separate facts.
- [ ] Source and destination histories both preserve the reattribution with the correct active/inactive presentation.
- [ ] Owner/support correction history can link to related invoices without exposing those links on public/print surfaces.
- [ ] Force delete is blocked while unresolved bookkeeping blockers remain across detected payments, ignored rows, manual adjustments, and active reattribution source/destination cases.
- [ ] `./vendor/bin/sail artisan test`

### Browser QA
- [ ] Ignore, restore, and reattribute recover truthful visible invoice state.
- [ ] Ignored rows are excluded from paid/outstanding calculations, restore reverses that cleanly, and reattribute updates both source and destination invoices immediately.
- [ ] Owner-visible audit/provenance context remains understandable after reattribution while public/print surfaces reflect only the truthful active accounting.
- [ ] Reattribution does not allow untruthful queued payment-triggered mail to send.
- [ ] Stale-address wrong-invoice cases do not trigger unsupported-wallet UI by themselves.
- [ ] Manual adjustment rows cannot be ignored or reattributed through the payment-correction flow.
- [ ] Force delete is blocked with clear resolution guidance while unresolved bookkeeping blockers remain, including source-invoice guidance for active reattributions, without auto-converting anything for the owner.
