# MS16 - Mailer & Alerts Polish + Audit

Status: Active as of 2026-03-27.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](../ops/RC_ROLLOUT_CHECKLIST.md)

This is the milestone execution doc for MS16. It tracks milestone-level objectives plus phase-level progress only.

## Milestone Objectives
- Stop the current runaway/spam-prone outbound-mail behavior before expanding the broader notifications surface.
- Add app-side safety controls such as rate limiting, dedupe/cooldown hardening, and any needed circuit-breaker behavior so outbound email stays provider-safe.
- Determine the current Mailgun account/sendability state and define the recovery path if sending has been blocked or degraded.
- Decide whether MS16 should keep SMTP or move outbound mail onto the Mailgun HTTP API.
- Finish the remaining mailer/alerts polish and audit work once sending is trustworthy again.

## Current Focus
- Active phase: **Phase 1**
- Current objective: review the notifications spec and lock the initial MS16 safety/recovery scope before broader notification polish.
- Primary surfaces: [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), current mailer/delivery implementation, and Mailgun account state.

## Phase Rollup
1. [ ] Phase 1 - Mailer Safety + Spam-Bug Triage
   Identify the runaway-send failure mode, define the app-side safeguards required before broader mailer work, and decide whether any notification behaviors need owner confirmation/validation before outbound mail can safely send.
2. [ ] Phase 2 - Provider Recovery + Transport Decision
   Determine whether Mailgun is currently blocking or throttling us, decide what recovery action is required, and choose whether MS16 stays on SMTP or moves to the Mailgun HTTP API.
3. [ ] Phase 3 - Notifications Polish + Audit
   Resume the broader notifications/alerts polish once the delivery path is trustworthy again, including any later-payment validation work, delivery-log polish, and related alert-behavior hardening.

## Open Scope Questions
- During MS14 Browser QA we found evidence that some payment-triggered outgoing mail may need owner confirmation/validation first to catch ignored, reattributed, or otherwise semantically ambiguous payment states. MS16 should decide whether that remains a narrow later-payment safeguard or becomes a broader notification rule.

## Exit Criteria
- [ ] The runaway/spam-prone outbound-mail bug is understood and fixed.
- [ ] App-side delivery safeguards are in place and documented.
- [ ] Mailgun sendability is restored or an explicit alternate path is chosen.
- [ ] The transport decision for MS16 (`SMTP` vs `Mailgun HTTP API`) is documented and implemented if needed.
- [ ] The remaining notifications/alerts polish ships on top of a trustworthy delivery path.
