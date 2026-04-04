# MS17 - Product Readiness

Status: Active as of 2026-04-03.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md)

This is the milestone execution doc for MS17. It tracks milestone-level objectives and phase-level progress. Phase 2 is handled inline; Phases 1, 3, and 4 have breakout strategy docs.

## Milestone Objectives
- Replace all "owner" terminology with "issuer" in UI copy, mail templates, and docs so the product and its documentation speak to the actual audience.
- Understand and scope the test suite before RC so coverage is intentional, not accidental.
- Deliver service health monitoring integrated into the support dashboard.
- Add post-payment onboarding so paid-state invoices have a clear issuer path to receipt delivery and ledger review.

## Current Focus
- Active phase: **Phase 2 - Test suite rationalization** (or Phase 3 — can run in parallel)
- Phase 1 complete: [`docs/strategies/17.1_ISSUER_SWEEP.md`](../strategies/17.1_ISSUER_SWEEP.md)
- Phase 2 next doc: [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../strategies/17.2_TEST_RATIONALIZATION.md)
- Phase 3 next doc: [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md)

## Phase Rollup

### Phase 1 — "owner" → "issuer" sweep ✓
Full rename across UI copy, URLs, route names, code variables and method names, mail classes, mail templates, delivery type strings, database columns, and tests. See [`docs/strategies/17.1_ISSUER_SWEEP.md`](../strategies/17.1_ISSUER_SWEEP.md) for the ordered checklist.

### Phase 2 — Test suite rationalization
The suite has grown large and was written opportunistically. This phase audits every test file, produces a written recommendation table, gets approval, and executes agreed changes so the suite is intentional going into RC. See [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../strategies/17.2_TEST_RATIONALIZATION.md).

### Phase 3 — Support UI + monitoring
The support UI is already complete. This phase adds a service health monitoring panel to the support dashboard so a support agent can triage operational issues — queue depth, recent delivery failures, watcher health — without raw log access. See [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md).

### Phase 4 — Post-payment onboarding
Extends the getting-started flow (MS13 task 11) with a Part 2 receipt step that activates once a paid invoice is receipt-eligible and completes when the issuer sends the first reviewed client receipt. See [`docs/strategies/17.4_POST_PAYMENT_ONBOARDING.md`](../strategies/17.4_POST_PAYMENT_ONBOARDING.md).

## Exit Criteria
- [ ] No "owner" copy remains in UI, mail templates, or docs where "issuer" is the correct term.
- [ ] Test suite is intentional and passes cleanly.
- [ ] Support UI and service health monitoring are usable by a support agent.
- [ ] Paid invoices have a clear issuer path to receipt delivery and ledger review.
