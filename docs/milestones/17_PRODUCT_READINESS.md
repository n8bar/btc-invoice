# MS17 - Product Readiness

Status: Complete as of 2026-04-08.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md)

This is the milestone execution doc for MS17. It tracks milestone-level objectives and phase-level progress. Phase 2 is handled inline; Phases 1, 3, 4, and 5 have breakout strategy docs.

## Milestone Objectives
- Replace all "owner" terminology with "issuer" in UI copy, mail templates, and docs so the product and its documentation speak to the actual audience.
- Understand and scope the test suite before RC so coverage is intentional, not accidental.
- Deliver service health monitoring integrated into the support dashboard.
- Add post-payment onboarding so paid-state invoices have a clear issuer path to receipt delivery and ledger review.

## Current Focus
- Milestone complete. All five phases done.
- Phase 1: [`docs/strategies/17.1_ISSUER_SWEEP.md`](../strategies/17.1_ISSUER_SWEEP.md)
- Phase 2: [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../strategies/17.2_TEST_RATIONALIZATION.md)
- Phase 3: [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md)
- Phase 4: [`docs/strategies/17.4_POST_PAYMENT_ONBOARDING.md`](../strategies/17.4_POST_PAYMENT_ONBOARDING.md)
- Phase 5: [`docs/strategies/17.5_MAIL_AUDIT.md`](../strategies/17.5_MAIL_AUDIT.md)

## Phase Rollup

### Phase 1 — "owner" → "issuer" sweep ✓
Full rename across UI copy, URLs, route names, code variables and method names, mail classes, mail templates, delivery type strings, database columns, and tests. See [`docs/strategies/17.1_ISSUER_SWEEP.md`](../strategies/17.1_ISSUER_SWEEP.md) for the ordered checklist.

### Phase 2 — Test suite rationalization ✓
The suite has grown large and was written opportunistically. This phase audits every test file, produces a written recommendation table, gets approval, and executes agreed changes so the suite is intentional going into RC. See [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../strategies/17.2_TEST_RATIONALIZATION.md).

### Phase 3 — Support UI + monitoring ✓
The support UI is already complete. This phase adds a service health monitoring panel to the support dashboard so a support agent can triage operational issues — queue depth, recent delivery failures, watcher health — without raw log access. See [`docs/strategies/17.3_SUPPORT_UI_AND_MONITORING.md`](../strategies/17.3_SUPPORT_UI_AND_MONITORING.md).

### Phase 4 — Post-payment onboarding ✓
Extends the getting-started flow (MS13 task 11) with a Part 2 receipt step that activates once a paid invoice is receipt-eligible and completes when the issuer sends the first reviewed client receipt. See [`docs/strategies/17.4_POST_PAYMENT_ONBOARDING.md`](../strategies/17.4_POST_PAYMENT_ONBOARDING.md).

### Phase 5 — Mail audit and hardening ✓
Six RC-blocking findings surfaced during Phase 4 BQA, plus additional findings discovered during implementation. All resolved. Root cause analysis and full fix details are in [`docs/strategies/17.5_MAIL_AUDIT.md`](../strategies/17.5_MAIL_AUDIT.md) (see the "Root causes" section at the top).

**Finding 1 — Receipt TXID wraps poorly on narrow screens.** ✓
Fixed: TXID wrapped in an inline-styled `word-break: break-all` span in `invoice-paid.blade.php`.

**Finding 2 — Past-due notice USD value is falsely marked approximate.** ✓
Fixed: "approximately" and "(approx.)" qualifiers removed from both past-due templates.

**Finding 3 — Past-due notices fire too frequently.** ✓
Root cause: `sendPastDueAlerts()` wrote a new skipped row on every cron run for already-sent slots. Fixed with a `deliveryExists()` guard that skips `queue()` when all slots already have a delivery row. Accumulated noise rows purged via cleanup migration (126 rows deleted).

**Finding 4 — Some invoices have thousands of delivery log entries.** ✓
Root cause: shared with Finding 3 — the slot loop wrote skipped rows unconditionally on every cron cycle. The same `deliveryExists()` guard resolves it. See the "Root causes" section in 17.5 for full detail.

**Finding 5 — Reply-to address is no-reply@cryptozing.app.** ✓
Fixed: `replyTo($invoice->user->email, $invoice->user->name)` added to the `envelope()` method of all seven client-facing Mailable classes.

**Finding 6 — Invoice delivery email shows "Draft" status.** ✓
Fixed: `{{ strtoupper($invoice->status ?? 'draft') }}` replaced with the literal `Open` in `invoice-ready.blade.php`.

**Findings 7–9 — Underpay/overpay alert log bloat + skipped-row display** ✓
Discovered during implementation. Underpayment and overpayment alert services had the same runaway-skipped-row pattern as Finding 3/4. Fixed with matching `deliveryExists()` guards. 65,747 accumulated noise rows purged via cleanup migration. Delivery log display now filters out skipped rows.

## Exit Criteria
- [x] No "owner" copy remains in UI, mail templates, or docs where "issuer" is the correct term.
- [x] Test suite is intentional and passes cleanly.
- [x] Support UI and service health monitoring are usable by a support agent.
- [x] Paid invoices have a clear issuer path to receipt delivery and ledger review.
- [x] All Phase 5 mail audit findings resolved: template defects corrected, delivery log bloat eliminated (65,873 noise rows purged), skipped rows filtered from the delivery log display, Browser QA passed.
