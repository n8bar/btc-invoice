# MS14 Phase 5 Strategy - Correction Tooling + Safeguards

Status: Active. Remaining work is the human Browser QA pass.
Parent milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](../milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)
Canonical requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

This strategy owns the execution order for the remaining Phase 5 work. Use the milestone doc for phase rollup and the spec for behavior and invariants.

- Ignore/restore/reattribute correction metadata now lives on `invoice_payments` with immutable source provenance and one active accounting destination.
- Owner-only ignore/restore/reattribute actions now ship from the invoice payment-history table with inline confirmation.
- Owner/support history keeps ignored and reattributed rows visible for audit while public/print/dashboard math follows only the active accounting destination.
- Watcher sync preserves ignored rows, queued payment-triggered deliveries made untruthful by correction are skipped, and force delete now has blocker guidance plus an FK backstop.
- Automated Phase 5 coverage now includes reattribution, dashboard accounting-destination behavior, and hard-delete blockers; `./vendor/bin/sail artisan test` passed on 2026-03-23 (`256 passed`).

## 1. Add reattribution state to `invoice_payments`
1. [x] Add `accounting_invoice_id`, `reattributed_at`, `reattributed_by_user_id`, and `reattribute_reason`.
2. [x] Keep `invoice_id` as immutable source provenance and use `accounting_invoice_id` as the sole active accounting destination.

## 2. Implement reattribution ledger behavior and recomputation
1. [x] Recompute both source and destination invoices immediately after reattribution.
2. [x] Re-run the same post-payment truthfulness checks so queued payment-triggered deliveries are suppressed when reattribution makes them untruthful.
3. [x] Keep stale-address wrong-invoice cases as correction work, not unsupported-wallet evidence by default.

## 3. Ship the owner reattribute flow
1. [x] Add owner-only route/controller/form handling for `PATCH /invoices/{invoice}/payments/{payment}/reattribute`.
2. [x] Enforce same-owner destination validation and keep manual adjustment rows out of the correction flow.
3. [x] Preserve selected destination and typed reason on validation failure.

## 4. Render reattribution truthfully across surfaces
1. [x] Source owner history shows reattributed-out rows without counting them.
2. [x] Destination owner history shows reattributed-in rows as active credit.
3. [x] Public/print show the payment only on the destination invoice, without source provenance, related-invoice links, or reattribution labels.
4. [x] Owner/support related-invoice links stay off public/print surfaces.

## 5. Finish destructive-delete safeguards
1. [x] Add one shared preflight guard for force delete.
2. [x] Choose and implement the persistence-layer hard-delete backstop.
3. [x] Keep detected payments, ignored rows, manual adjustments, and active reattribution source/destination cases blocking force delete until intentionally resolved.

## 6. Complete automated coverage
1. [x] Reattribution recomputes source and destination invoices truthfully.
2. [x] Same-owner destination validation holds.
3. [x] `invoice_payments` keeps immutable source provenance while only the active accounting destination and current reattribution state change.
4. [x] Reattribution audit logs and metadata persist.
5. [x] Reattribution suppresses queued payment-triggered deliveries that would become untruthful.
6. [x] Stale-address wrong-invoice cases stay outside unsupported-wallet evidence without separate facts.
7. [x] Source and destination histories preserve the correct reattributed-out / reattributed-in presentation.
8. [x] Owner/support related-invoice links stay off public/print surfaces.
9. [x] Force delete stays blocked across all unresolved bookkeeping blocker classes.
10. [x] `./vendor/bin/sail artisan test`

## 7. Prepare Browser QA
- Agent-run only. Complete this prep before handing the browser pass to a human.

1. [x] Start the local stack with `./vendor/bin/sail up -d` and leave the `scheduler` service running.
2. [x] Confirm the controlled MS14 baseline from [`docs/strategies/MS14_PHASE1_BASELINE_RESEED.md`](MS14_PHASE1_BASELINE_RESEED.md) is present. If invoices `50` through `58` are missing, stop and rebuild that baseline first.
3. [x] Leave queue jobs undrained while testing payment-correction suppression. This stack has a scheduler but no always-on queue worker, so use the invoice delivery log as the source of truth for `queued` -> `skipped`.
4. [x] Prepare Scenario A for ignore/paid rollback:
   1. [x] Create a sent invoice with a client email and enable its public link.
   2. [x] Turn on `Settings > Notifications > Auto email paid receipts`.
   3. [x] Send one `testnet4` payment that fully pays the invoice from local-only funding material stored outside the repo boundary (for example under `.cybercreek/`).
   4. [x] Run `./vendor/bin/sail artisan wallet:watch-payments --invoice={invoiceId}` until the invoice becomes `paid`.
   5. [x] Open the invoice delivery log and verify `receipt` and `owner_paid_notice` rows are `Queued`.
5. [x] Prepare Scenario B for restore/underpay cleanup:
   1. [x] Create a second sent invoice with the same owner and a client email.
   2. [x] Send three confirmed `testnet4` payments so the invoice reaches `paid`.
   3. [x] Run `./vendor/bin/sail artisan wallet:watch-payments --invoice={invoiceId}` until the invoice shows `paid`.
   4. [x] Prepare one payment row in the ignored state so the invoice falls back to `partial` while at least two active payments remain.
   5. [x] Reopen the invoice delivery log and verify `client_underpay_alert`, `owner_underpay_alert`, `client_partial_warning`, and `owner_partial_warning` rows are `Queued`.
6. [x] Prepare Scenario C for same-owner reattribution:
   1. [x] Create source invoice A and destination invoice B for the same owner.
   2. [x] Enable the public link on both invoices.
   3. [x] Send one confirmed `testnet4` payment to source invoice A and run `./vendor/bin/sail artisan wallet:watch-payments --invoice={sourceInvoiceId}` until the payment row appears.
   4. [x] After destination invoice B exists, send one additional `testnet4` payment to source invoice A's old address and rerun `./vendor/bin/sail artisan wallet:watch-payments --invoice={sourceInvoiceId}` until the later payment row appears.
7. [x] Update items 1 through 8 in section 8 below with the actual Scenario A/B/C invoice IDs, payment rows, and expected queued delivery-log rows from prep.
   Scenario A: invoice `67` / `INV-0003`, payment row `39` (`b03c10f72550ff2219e3537168e64e9b95446cc62c758aec0a55d1cb3d9e2e0b`, `10,000 sats`), delivery rows `6624` (`receipt`) and `6625` (`owner_paid_notice`), public URL `http://192.168.68.25/p/WwzL9gBjLjwgsuUhIqdiiFPajpupv8jC6KlPGskPECAoFwFP`.
   Scenario B: invoice `68` / `INV-0004`, ignored payment row `44` (`777961b56075033e6c8a905576fd43f5514d00259825cd293911cebc16dc2af2`, `40,000 sats`), queued delivery rows `6616`-`6619` (`client_underpay_alert`, `owner_underpay_alert`, `client_partial_warning`, `owner_partial_warning`), public URL `http://192.168.68.25/p/zzWOMQZhEigBcumiLC7yphiZxhGD8chueFPOwHCy26vYSFvW`.
   Scenario C: source invoice `69` / `INV-0005`, first/source payment row `85` (`51cabfecb8e2d38241bf0c980e46607ef4f8725dd734f91ae26e4eddc8cb5be0`, `30,000 sats`), later payment row `84` (`0705721bf4b671a0d528cc3e4a2d7cf925e409018080c0b1a6513ccfafd2ebc5`, `40,000 sats`), destination invoice `70` / `INV-0006`, source public URL `http://192.168.68.25/p/QQiGx6nid7P5Vvnm1SbnyUrvAPEi0RHCE0fhHAg3WleEaDUl`, destination public URL `http://192.168.68.25/p/y1HYkdrhcczUAOsfIaXMqTpYvIN5d1AZJnpc065evYaTV7TN`.

## 8. Run Browser QA
1. [x] Log in as `antonina12@nospam.site` with password `password`.
2. [x] Ignore a paid payment and verify paid-state rollback:
   1. [x] Open invoice `67` (`INV-0003`) and click `Ignore` on payment row `39`.
   2. [x] Enter a reason and submit.
   3. [x] Verify invoice `67` leaves `paid`, settlement math reopens truthfully, payment row `39` remains visible in owner history as ignored, and delivery rows `6624` and `6625` change from `Queued` to `Skipped`.
   4. [x] Open invoice `67` print/public surfaces and verify payment row `39` does not appear in payment history or totals.
3. [x] Restore an ignored payment and verify alert suppression cleanup:
   1. [x] Open invoice `68` (`INV-0004`) and click `Restore` on ignored payment row `44`.
   2. [x] Verify invoice `68` returns to the truthful paid state, payment row `44` clears its ignore metadata, and delivery rows `6616` through `6619` change from `Queued` to `Skipped`.
4. [ ] Reattribute a payment to a same-owner destination invoice:
   1. [ ] On source invoice `69` (`INV-0005`), click `Reattribute` on the `30,000 sats` / `$21.06` payment detected `Mon, Mar 23, 2026 11:41 PM`, choose destination invoice `70` (`INV-0006`), enter a reason, and submit.
   2. [ ] Verify source invoice `69` owner history shows that `30,000 sats` / `$21.06` payment as reattributed out and no longer counting there.
   3. [ ] Verify destination invoice `70` owner history shows that same `30,000 sats` / `$21.06` payment as reattributed in and counting there.
5. [ ] Verify public and print surfaces after reattribution:
   1. [ ] Open source invoice `69` public/print surfaces and verify the `30,000 sats` / `$21.06` payment is absent there while the later `40,000 sats` / `$28.08` payment detected `Mon, Mar 23, 2026 11:27 PM` still appears as the active source payment.
   2. [ ] Open destination invoice `70` public/print surfaces and verify the `30,000 sats` / `$21.06` payment is present and counted there.
   3. [ ] Verify neither public/print surface exposes source provenance, related-invoice links, or reattribution labels.
6. [ ] Verify the stale-address wrong-invoice boundary:
   1. [ ] Use the later `40,000 sats` / `$28.08` payment detected `Mon, Mar 23, 2026 11:27 PM` on source invoice `69`.
   2. [ ] Verify that `40,000 sats` / `$28.08` payment appears as a normal correction candidate on source invoice `69`.
   3. [ ] Verify unsupported-wallet UI does not appear solely because of that later payment.
   4. [ ] Reattribute that `40,000 sats` / `$28.08` payment to destination invoice `70` and re-check the same owner/public behavior there.
7. [ ] Verify manual-adjustment guardrails:
   1. [ ] Create a manual adjustment row through the existing adjustment flow on invoice `67`, `68`, `69`, or `70`.
   2. [ ] Verify that row shows no `Ignore`, `Restore`, or `Reattribute` controls.
8. [ ] Verify force-delete guidance:
   1. [ ] After reattributing either the `30,000 sats` / `$21.06` payment or the `40,000 sats` / `$28.08` payment from source invoice `69`, attempt force delete on destination invoice `70` (`INV-0006`).
   2. [ ] Verify force delete is blocked, the blocker is named clearly, source-invoice guidance points back to invoice `69`, and the flow offers no one-click auto-conversion or cleanup.
