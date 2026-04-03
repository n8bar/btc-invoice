# MS17 - Product Readiness

Status: Active as of 2026-04-03.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), [`docs/specs/MANUAL_PAYMENTS.md`](../specs/MANUAL_PAYMENTS.md)

This is the milestone execution doc for MS17. It tracks milestone-level objectives and phase-level progress. Phases 1 and 2 are handled inline; Phases 3–5 have breakout strategy docs.

## Milestone Objectives
- Replace all "owner" terminology with "issuer" in UI copy, mail templates, and docs so the product and its documentation speak to the actual audience.
- Understand and scope the test suite before RC so coverage is intentional, not accidental.
- Add off-chain payment recording so issuers can close invoices paid by wire, cash, or check without voiding and recreating them.
- Deliver a minimum viable support UI with service health monitoring integrated into the support dashboard.
- Add post-payment onboarding so paid-state invoices have a clear issuer path to receipt delivery and ledger review.

## Current Focus
- Active phase: **Phase 1 - "owner" → "issuer" sweep**
- Primary next doc: this doc (inline phases 1–2); [`docs/strategies/17.3_OFF_CHAIN_PAYMENTS.md`](../strategies/17.3_OFF_CHAIN_PAYMENTS.md) for Phase 3.

## Phase Rollup

### Phase 1 — "owner" → "issuer" sweep (inline)
Find and replace all instances of "owner" with "issuer" across UI views, mail templates, and docs. The word "owner" must not appear in any copy visible to the app user, and docs should reflect correct terminology going forward.

- [x] Audit all Blade views and mail templates for "owner" in user-facing copy.
- [x] Audit all docs (PLAN, milestone docs, strategy docs, specs, ops docs) for "owner" used to mean the invoice issuer.
- [x] Replace each instance with "issuer" or rephrase naturally where a direct swap reads awkwardly.
- [ ] Browser-verify: spot-check invoice, dashboard, mail previews, and support UI for any remaining "owner" copy.
- [ ] Commit and push.

### Phase 2 — Test suite rationalization
The suite has grown large and was written opportunistically. This phase audits every test file, produces a written recommendation table, gets approval, and executes agreed changes so the suite is intentional going into RC. See [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../strategies/17.2_TEST_RATIONALIZATION.md).

### Phase 3 — Off-chain payment recording
Add an issuer-facing form to record payments received outside the Bitcoin network (wire transfer, cash, check), placed alongside the existing payment correction tools, so invoices can reach paid status without on-chain workarounds. Touches the invoice payment history UI, the `InvoicePayment` model (manual flag, no txid), and the payment ledger recalculation path. Manual payments must be visually distinct from on-chain payments in the history table. If the manual payment closes the balance, the invoice transitions to `paid` and the receipt panel becomes available. See [`docs/strategies/17.3_OFF_CHAIN_PAYMENTS.md`](../strategies/17.3_OFF_CHAIN_PAYMENTS.md).

### Phase 4 — Support UI + monitoring
Complete and harden the minimum viable support UI for RC, with service health monitoring integrated into the support dashboard so operational issues are visible without digging through logs. The support UI already has basic invoice and client read access; this phase fills in whatever gaps remain for RC and adds a monitoring surface — queue depth, recent delivery failures, watcher health — so a support agent can triage without raw log access. See [`docs/strategies/17.4_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.4_SUPPORT_UI_AND_MONITORING.md).

### Phase 5 — Post-payment onboarding
Ensure paid-state invoices have a clear issuer path to receipt delivery and ledger review — closing the loop between payment detection and issuer-confirmed client communication. The receipt send path already exists; this phase is about surfacing it clearly at the right moment (paid invoice without a sent receipt) so issuers don't miss it. Touches dashboard and invoice show views, and potentially a nudge in the issuer paid notice email. See [`docs/strategies/17.5_POST_PAYMENT_ONBOARDING.md`](../strategies/17.5_POST_PAYMENT_ONBOARDING.md).

## Exit Criteria
- [ ] No "owner" copy remains in UI, mail templates, or docs where "issuer" is the correct term.
- [ ] Test suite is intentional and passes cleanly.
- [ ] Issuers can record off-chain payments and close invoices without voiding them.
- [ ] Support UI and service health monitoring are usable by a support agent.
- [ ] Paid invoices have a clear issuer path to receipt delivery and ledger review.
