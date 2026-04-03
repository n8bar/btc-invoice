# MS17 - Product Readiness

Status: Active as of 2026-04-03.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), [`docs/specs/MANUAL_PAYMENTS.md`](../specs/MANUAL_PAYMENTS.md)

This is the milestone execution doc for MS17. It tracks milestone-level objectives and phase-level progress. Phases 1 and 2 are handled inline; Phases 3–5 have breakout strategy docs.

## Milestone Objectives
- Replace all "owner" terminology with "issuer" in UI copy and mail templates so the product speaks to its actual audience.
- Understand and scope the test suite before RC so coverage is intentional, not accidental.
- Add off-chain payment recording so issuers can close invoices paid by wire, cash, or check without voiding and recreating them.
- Deliver a minimum viable support UI with service health monitoring integrated into the support dashboard.
- Add post-payment onboarding so paid-state invoices have a clear issuer path to receipt delivery and ledger review.

## Current Focus
- Active phase: **Phase 1 - "owner" → "issuer" copy sweep**
- Primary next doc: this doc (inline phases 1–2); [`docs/strategies/17.3_OFF_CHAIN_PAYMENTS.md`](../strategies/17.3_OFF_CHAIN_PAYMENTS.md) for Phase 3.

## Phase Rollup

### Phase 1 — "owner" → "issuer" copy sweep (inline)
Find and replace all user-facing instances of "owner" with "issuer" across UI views and mail templates. The word "owner" must not appear in any copy visible to the app user.

- [ ] Audit all Blade views and mail templates for "owner" in user-facing copy.
- [ ] Replace each instance with "issuer" or rephrase naturally where a direct swap reads awkwardly.
- [ ] Browser-verify: spot-check invoice, dashboard, mail previews, and support UI for any remaining "owner" copy.
- [ ] Commit and push.

### Phase 2 — Test suite rationalization (inline)
Scope the existing test suite and decide what to keep, remove, or add before RC. This phase requires a scoping conversation before implementation begins.

- [ ] Scoping conversation: review what the test suite currently covers and identify gaps, redundancies, or tests that have drifted from the current product behavior.
- [ ] Agree on the rationalization plan (what to add, what to drop, what to rename/reorganize).
- [ ] Execute the plan.
- [ ] Confirm the suite passes cleanly and coverage intent is documented.

### Phase 3 — Off-chain payment recording
- [ ] See [`docs/strategies/17.3_OFF_CHAIN_PAYMENTS.md`](../strategies/17.3_OFF_CHAIN_PAYMENTS.md)

### Phase 4 — Support UI + monitoring
- [ ] See [`docs/strategies/17.4_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.4_SUPPORT_UI_AND_MONITORING.md)

### Phase 5 — Post-payment onboarding
- [ ] See [`docs/strategies/17.5_POST_PAYMENT_ONBOARDING.md`](../strategies/17.5_POST_PAYMENT_ONBOARDING.md)

## Exit Criteria
- [ ] No user-visible "owner" copy remains in UI or mail templates.
- [ ] Test suite is intentional and passes cleanly.
- [ ] Issuers can record off-chain payments and close invoices without voiding them.
- [ ] Support UI and service health monitoring are usable by a support agent.
- [ ] Paid invoices have a clear issuer path to receipt delivery and ledger review.
