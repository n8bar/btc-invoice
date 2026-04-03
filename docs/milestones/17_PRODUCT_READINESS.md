# MS17 - Product Readiness

Status: Active as of 2026-04-03.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md)

This is the milestone execution doc for MS17. It tracks milestone-level objectives and phase-level progress. Phases 1 and 2 are handled inline; Phases 3–4 have breakout strategy docs.

## Milestone Objectives
- Replace all "owner" terminology with "issuer" in UI copy, mail templates, and docs so the product and its documentation speak to the actual audience.
- Understand and scope the test suite before RC so coverage is intentional, not accidental.
- Deliver service health monitoring integrated into the support dashboard.
- Add post-payment onboarding so paid-state invoices have a clear issuer path to receipt delivery and ledger review.

## Current Focus
- Active phase: **Phase 1 - "owner" → "issuer" sweep**
- Primary next doc: this doc (inline phases 1–2); [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md) for Phase 3.

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

### Phase 3 — Support UI + monitoring
The support UI is already complete. This phase adds a service health monitoring panel to the support dashboard so a support agent can triage operational issues — queue depth, recent delivery failures, watcher health — without raw log access. See [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md).

### Phase 4 — Post-payment onboarding
Extends the getting-started flow (MS13 task 11) with a Part 2 receipt step that activates once a paid invoice is receipt-eligible and completes when the issuer sends the first reviewed client receipt. See [`docs/strategies/17.4_POST_PAYMENT_ONBOARDING.md`](../strategies/17.4_POST_PAYMENT_ONBOARDING.md).

## Exit Criteria
- [ ] No "owner" copy remains in UI, mail templates, or docs where "issuer" is the correct term.
- [ ] Test suite is intentional and passes cleanly.
- [ ] Support UI and service health monitoring are usable by a support agent.
- [ ] Paid invoices have a clear issuer path to receipt delivery and ledger review.
