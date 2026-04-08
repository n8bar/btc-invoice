# MS17 - Product Readiness

Status: Active as of 2026-04-03.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md)

This is the milestone execution doc for MS17. It tracks milestone-level objectives and phase-level progress. Phase 2 is handled inline; Phases 1, 3, 4, and 5 have breakout strategy docs.

## Milestone Objectives
- Replace all "owner" terminology with "issuer" in UI copy, mail templates, and docs so the product and its documentation speak to the actual audience.
- Understand and scope the test suite before RC so coverage is intentional, not accidental.
- Deliver service health monitoring integrated into the support dashboard.
- Add post-payment onboarding so paid-state invoices have a clear issuer path to receipt delivery and ledger review.

## Current Focus
- Active phase: **Phase 5 — Mail audit and hardening**
- Phase 1 complete: [`docs/strategies/17.1_ISSUER_SWEEP.md`](../strategies/17.1_ISSUER_SWEEP.md)
- Phase 2 complete: [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../strategies/17.2_TEST_RATIONALIZATION.md)
- Phase 3 complete: [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md)
- Phase 4 complete: [`docs/strategies/17.4_POST_PAYMENT_ONBOARDING.md`](../strategies/17.4_POST_PAYMENT_ONBOARDING.md)
- Phase 5 active: [`docs/strategies/17.5_MAIL_AUDIT.md`](../strategies/17.5_MAIL_AUDIT.md)

## Phase Rollup

### Phase 1 — "owner" → "issuer" sweep ✓
Full rename across UI copy, URLs, route names, code variables and method names, mail classes, mail templates, delivery type strings, database columns, and tests. See [`docs/strategies/17.1_ISSUER_SWEEP.md`](../strategies/17.1_ISSUER_SWEEP.md) for the ordered checklist.

### Phase 2 — Test suite rationalization ✓
The suite has grown large and was written opportunistically. This phase audits every test file, produces a written recommendation table, gets approval, and executes agreed changes so the suite is intentional going into RC. See [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../strategies/17.2_TEST_RATIONALIZATION.md).

### Phase 3 — Support UI + monitoring ✓
The support UI is already complete. This phase adds a service health monitoring panel to the support dashboard so a support agent can triage operational issues — queue depth, recent delivery failures, watcher health — without raw log access. See [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md).

### Phase 4 — Post-payment onboarding ✓
Extends the getting-started flow (MS13 task 11) with a Part 2 receipt step that activates once a paid invoice is receipt-eligible and completes when the issuer sends the first reviewed client receipt. See [`docs/strategies/17.4_POST_PAYMENT_ONBOARDING.md`](../strategies/17.4_POST_PAYMENT_ONBOARDING.md).

### Phase 5 — Mail audit and hardening
Five findings surfaced during Phase 4 BQA that are RC-blocking. See [`docs/strategies/17.5_MAIL_AUDIT.md`](../strategies/17.5_MAIL_AUDIT.md).

**Finding 1 — Receipt TXID wraps poorly on narrow screens.**
The transaction ID in receipt emails overflows its container on mobile/narrow viewports. Needs word-break or truncation treatment in the mail template.

**Finding 2 — Past-due notice USD value is falsely marked approximate.**
The past-due notice copy says the USD amount is approximate but gives a to-the-cent figure. Drop the "approximate" qualifier and show the exact amount.

**Finding 3 — Past-due notices fire too frequently.**
Some invoices are receiving far more past-due notices than intended. The scheduled/throttle behavior needs auditing and fixing.

**Finding 4 — Some invoices have thousands of delivery log entries.**
Likely related to Finding 3 — something is creating delivery rows at a runaway rate. Needs root cause investigation and a fix to prevent re-occurrence.

**Finding 5 — Reply-to address is no-reply@cryptozing.app.**
Replies to outbound mail go nowhere. The reply-to header needs to be set to a monitored address.

**Finding 6 — Invoice delivery email shows "Draft" status.**
The invoice status shown in the delivery email is "Draft" at send time. This is an internal issuer term and should never be client-facing. Use "Open" as the client-facing status label for invoices awaiting payment; reserve "Due" for date-related contexts (e.g. due date, past due).

## Exit Criteria
- [ ] No "owner" copy remains in UI, mail templates, or docs where "issuer" is the correct term.
- [x] Test suite is intentional and passes cleanly.
- [x] Support UI and service health monitoring are usable by a support agent.
- [ ] Paid invoices have a clear issuer path to receipt delivery and ledger review.
