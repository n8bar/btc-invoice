# MS14 Phase 5 Strategy - Correction Tooling + Safeguards

Status: Active.
Parent milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](../milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)
Canonical requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

Current implementation on 2026-03-19: correction metadata now lives on `invoice_payments`; owner-only ignore/restore actions ship from the invoice payment-history table with inline confirmation; ignored rows stay visible and marked in owner/support audit views while public/print/dashboard math excludes them; watcher sync preserves ignored rows; and queued receipts/paid notices/underpay/partial deliveries are skipped when a correction makes them untruthful. Phase 5 remains open because invoice-to-invoice reattribution is still planned work in the same correction track.

## 5.1 Add correction metadata
Add correction metadata to `invoice_payments` (or companion audit table):
- `ignored_at`, `ignored_by_user_id`, `ignore_reason`.

## 5.2 Add ignore/restore behavior
1. Update payment summaries and state logic so ignored on-chain rows are excluded from paid/outstanding calculations.
2. Preserve the original tx rows for audit.

Owner UI on invoice show/payment history:
- `Ignore` action per on-chain payment with warning copy.
- confirmation step requiring explicit intent.
- `Restore` action to reverse mistaken ignores.

Re-run payment state recomputation after ignore/restore.

## 5.3 Keep corrections guarded and auditable
1. Restrict ignore/restore to the owner.
2. Disallow ignoring manual adjustment rows.
3. Log every ignore/restore action with invoice/payment/user IDs.

## 5.4 Add invoice-to-invoice reattribution
1. Let the owner move a detected payment's accounting credit from invoice A to invoice B when the payment was real but the business intent belongs to B.
2. Keep this correction same-owner only in RC so reattribution cannot move money between unrelated owners.
3. Use an `invoice_payments`-centric storage model for RC:
   1. keep the original detected payment row as the canonical fact
   2. keep `invoice_id` as the immutable source/detected invoice while the row exists
   3. add one current accounting-destination field on that same row so only one active accounting destination exists at a time
4. Preserve provenance and auditability:
   1. record the current destination, if any, on the canonical payment row
   2. preserve who performed the current reassignment, when, and why
   3. rely on structured logs as the append-only history of reattribution events
5. Recompute both source and destination invoices immediately after reattribution so status, paid/outstanding totals, QR/BIP21 targets, and queued payment-triggered deliveries all stay truthful.
6. Keep wallet trust separate:
   1. reattribution fixes wrong-invoice bookkeeping
   2. reattribution does not by itself clear unsupported wallet or invoice evidence
7. Treat stale-address wrong-invoice reuse as a primary reattribution use case, not unsupported-wallet evidence by itself.
8. Keep owner-visible history on both invoices:
   1. source invoice shows the payment as reattributed out and no longer counting there
   2. destination invoice shows the payment as reattributed in and counting there
   3. source styling may use strike-through or similar de-emphasis, but visibility is mandatory
9. Add destructive-delete safeguards:
   1. soft delete may remain allowed because provenance survives
   2. force delete must be blocked while unresolved bookkeeping blockers remain on or against the invoice
   3. the purge path requires the owner to intentionally remove or resolve each blocker class first
   4. detected payment rows, ignored rows, and manual adjustments remain blockers until intentionally removed as part of purge
   5. if the block is caused by an active reattribution, destination delete attempts must direct the owner back to the source invoice to resolve it first
   6. once an active reattribution is resolved, any remaining retained payment row still blocks source force delete until purged
   7. delete flows may link to implicated invoices but must not auto-convert anything
   8. choose and implement the persistence-layer hard-delete backstop, then route every force-delete path through the same preflight guard so destructive deletes cannot bypass the bookkeeping-history rule
10. Allow owner/support history rows that reference another invoice to link to that invoice when it is still available, while keeping those links off public/print surfaces.

## 5.5 Verify Phase 5
Automated / command verification:
1. [x] Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 5 work.
   - Current result on 2026-03-19: `246 passed`.
2. [x] Add or expand automated coverage for:
   1. [x] ignore/restore payment recalculation
   2. [x] authorization and owner-only safeguards
   3. [x] audit logging and metadata persistence
   4. [x] public/print/dashboard exclusion of ignored rows
   5. [x] watcher persistence of ignored rows and skipped queued deliveries when corrections change truth
3. [ ] Add or expand automated coverage for:
   1. [ ] reattributing a payment from invoice A to invoice B recomputes both invoices truthfully
   2. [ ] same-owner guardrails and destination-invoice validation
   3. [ ] the canonical payment row keeps immutable source provenance while updating only the current accounting destination and current reattribution metadata
   4. [ ] provenance and audit-log persistence for reattribution
   5. [ ] queued payment-triggered deliveries affected by reattribution are skipped or otherwise suppressed when they would become untruthful, without relying on the deferred MS15 later-payment hold
   6. [ ] stale-address wrong-invoice cases do not become unsupported-wallet evidence without separate facts
   7. [ ] source and destination histories both preserve the reattribution with the correct active/inactive presentation
   8. [ ] owner/support correction history can link to related invoices without exposing those links on public/print surfaces
   9. [ ] force delete is blocked while unresolved bookkeeping blockers remain across detected payments, ignored rows, manual adjustments, and active reattribution source/destination cases, with app-level guidance and a persistence-layer backstop
4. [x] Verify raw tx history remains present after ignore/restore.
   - Current result on 2026-03-19: owner and support payment-history views keep the original tx rows visible with ignored-state context while public/print surfaces exclude them.

Browser QA:
5. [ ] Exercise ignore, restore, and reattribute in the browser and confirm visible invoice state recovers truthfully.
6. [ ] Confirm ignored rows are excluded from paid/outstanding calculations, restore reverses that cleanly, and reattribute updates both source and destination invoices immediately.
7. [ ] Confirm owner-visible audit/provenance context remains understandable after reattribution while public/print surfaces only reflect the truthful active accounting.
8. [ ] Confirm reattribution does not allow untruthful queued payment-triggered mail to send, without depending on the deferred MS15 later-payment validation gate.
9. [ ] Confirm stale-address wrong-invoice cases do not trigger unsupported-wallet UI by themselves.
10. [ ] Confirm manual adjustment rows cannot be ignored or reattributed through the payment-correction flow.
11. [ ] Confirm force delete is blocked with clear resolution guidance while unresolved bookkeeping blockers remain, including source-invoice guidance for active reattributions, without auto-converting anything for the owner.
