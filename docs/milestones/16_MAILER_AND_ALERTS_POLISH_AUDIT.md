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
- Active phase: **Phase 1 - Delivery Baseline Audit**
- Current objective: lock the trustworthy outbound-mail baseline by inventorying the live delivery surface, identifying the runaway/spam-prone failure mode, and defining the stop-the-bleed safeguards that later provider and notification work depends on.
- Primary next doc: [`docs/strategies/16.1_DELIVERY_BASELINE_AUDIT.md`](../strategies/16.1_DELIVERY_BASELINE_AUDIT.md)
- Sequencing note: the phase breakout was re-cut from the actual dependency chain; later phases are sequential by default unless a phase strategy explicitly marks safe sidecars.
- Primary surfaces: [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), current delivery/alert code, and Mailgun account state.

## Phase Rollup
1. [ ] Phase 1 - [Delivery Baseline Audit](../strategies/16.1_DELIVERY_BASELINE_AUDIT.md)
   Inventory the live outbound surface, identify the runaway/spam-prone risk concretely, and lock the stop-the-bleed inputs that the rest of MS16 depends on.
2. [ ] Phase 2 - [Safeguards + Provider Recovery](../strategies/16.2_SAFEGUARDS_PROVIDER_RECOVERY.md)
   Implement the shared outbound-mail safeguards, determine actual provider/sendability state, and make the transport decision from evidence instead of inertia.
3. [ ] Phase 3 - [Payment Communication Truthfulness](../strategies/16.3_PAYMENT_COMMUNICATION_TRUTHFULNESS.md)
   Resolve acknowledgment-versus-receipt behavior, later-payment ambiguity handling, issuer-copy behavior, and alert-surface rationalization on top of the trustworthy delivery path.
4. [ ] Phase 4 - [Template Polish + RC Mail Readiness](../strategies/16.4_TEMPLATE_POLISH_RC_READINESS.md)
   Finish the remaining template/settings/delivery-log polish and rehearse alias-off RC mail readiness on the chosen transport.

## Exit Criteria
- [ ] The runaway/spam-prone outbound-mail bug is understood and fixed.
- [ ] App-side delivery safeguards are in place and documented.
- [ ] Mailgun sendability is restored or an explicit alternate path is chosen.
- [ ] The transport decision for MS16 (`SMTP` vs `Mailgun HTTP API`) is documented and implemented if needed.
- [ ] The remaining notifications/alerts/template polish ships on top of a trustworthy delivery path.
