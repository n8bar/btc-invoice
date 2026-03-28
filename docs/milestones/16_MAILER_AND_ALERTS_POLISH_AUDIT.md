# MS16 - Mailer & Alerts Polish + Audit

Status: Active as of 2026-03-27.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md), [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](../ops/RC_ROLLOUT_CHECKLIST.md)

This is the milestone execution doc for MS16. It tracks milestone-level objectives plus phase-level progress only.

## Milestone Objectives
- Stop the current runaway/spam-prone outbound-mail behavior before expanding the broader notifications surface.
- Add app-side safety controls such as rate limiting, dedupe/cooldown hardening, and any needed circuit-breaker behavior so outbound email stays provider-safe.
- Determine the current Mailgun account/sendability state and define the recovery path if sending has been blocked or degraded.
- Adopt Mailgun HTTP API as the intended outbound transport for MS16 unless a concrete blocking constraint forces a temporary fallback.
- Finish the remaining mailer/alerts polish and audit work once sending is trustworthy again.

## Current Focus
- Active phase: **Phase 2 - Safeguards + Provider Recovery**
- Current objective: implement the shared outbound-mail safeguards the Phase 1 audit identified, recover provider trust, and ship the Mailgun HTTP API path on top of that safer delivery baseline.
- Primary next doc: [`docs/strategies/16.2_SAFEGUARDS_PROVIDER_RECOVERY.md`](../strategies/16.2_SAFEGUARDS_PROVIDER_RECOVERY.md)
- Sequencing note: the phase breakout was re-cut from the actual dependency chain; later phases are sequential by default unless a phase strategy explicitly marks safe sidecars.
- Primary surfaces: [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), current delivery/alert code, and Mailgun account state.

## Phase Rollup
1. [x] Phase 1 - [Delivery Baseline Audit](../strategies/16.1_DELIVERY_BASELINE_AUDIT.md)
   Completed. The live outbound inventory, current guardrail matrix, and the concrete Phase 2 / Phase 3 inputs are now documented, including the current lack of a shared send-intent gate, the unbounded manual-send path, the live partial-warning drift, and the missing skip path for queued overpayment alerts.
2. [ ] Phase 2 - [Safeguards + Provider Recovery](../strategies/16.2_SAFEGUARDS_PROVIDER_RECOVERY.md)
   Implement the shared outbound-mail safeguards, determine actual provider/sendability state, and move the app onto Mailgun HTTP API unless a concrete blocking constraint prevents it.
3. [ ] Phase 3 - [Payment Communication Truthfulness](../strategies/16.3_PAYMENT_COMMUNICATION_TRUTHFULNESS.md)
   Resolve acknowledgment-versus-receipt behavior, later-payment ambiguity handling, issuer-copy behavior, and alert-surface rationalization on top of the trustworthy delivery path.
4. [ ] Phase 4 - [Template Polish + RC Mail Readiness](../strategies/16.4_TEMPLATE_POLISH_RC_READINESS.md)
   Finish the remaining template/settings/delivery-log polish and rehearse alias-off RC mail readiness on the chosen transport.

## Exit Criteria
- [ ] The runaway/spam-prone outbound-mail bug is understood and fixed.
- [ ] App-side delivery safeguards are in place and documented.
- [ ] Mailgun sendability is restored or an explicit alternate path is chosen.
- [ ] Mailgun HTTP API is documented as the chosen MS16 transport and implemented unless a concrete blocking constraint forces a temporary fallback.
- [ ] The remaining notifications/alerts/template polish ships on top of a trustworthy delivery path.
