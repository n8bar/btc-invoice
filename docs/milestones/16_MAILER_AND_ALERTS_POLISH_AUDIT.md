# MS16 - Mailer & Alerts Polish + Audit

Status: Active as of 2026-03-28.
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
- Active phase: **Phase 3 - Payment Communication Truthfulness + Notification UX**
- Current objective: finish Phase 3 Browser QA on the payment acknowledgment-versus-receipt split while landing the remaining active notification UX work there too: simple shared mail branding, clearer manual-receipt discovery, and invoice-page anchor/CTA cleanup.
- Primary next doc: [`docs/strategies/16.3_PAYMENT_COMMUNICATION_TRUTHFULNESS.md`](../strategies/16.3_PAYMENT_COMMUNICATION_TRUTHFULNESS.md)
- Sequencing note: the phase breakout was re-cut from the actual dependency chain; later phases are sequential by default unless a phase strategy explicitly marks safe sidecars.
- Primary surfaces: [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), current delivery/alert code, and Mailgun account state.

## Phase Rollup
1. [x] Phase 1 - [Delivery Baseline Audit](../strategies/16.1_DELIVERY_BASELINE_AUDIT.md)
   Completed. The live outbound inventory, current guardrail matrix, and the concrete Phase 2 / Phase 3 inputs are now documented, including the current lack of a shared send-intent gate, the unbounded manual-send path, the live partial-warning drift, and the missing skip path for queued overpayment alerts.
2. [x] Phase 2 - [Safeguards + Provider Recovery](../strategies/16.2_SAFEGUARDS_PROVIDER_RECOVERY.md)
   Completed. Shared outbound safeguards now run through a provider-backed Mailgun HTTP API path, the nested mailable queue bug was identified and removed so `DeliverInvoiceMail` is the real send boundary, controlled alias-off proof sends succeeded end-to-end, and Phase 3 can now focus on truthful payment communication semantics instead of delivery trust.
3. [ ] Phase 3 - [Payment Communication Truthfulness + Notification UX](../strategies/16.3_PAYMENT_COMMUNICATION_TRUTHFULNESS.md)
   The acknowledgment-versus-receipt split, txid-scoped acknowledgments, and paired delivery-history labels are implemented; the active remaining work is Phase 3 Browser QA plus the last active notification UX fixes needed to make the manual-receipt model obvious and the active mails branded before verification continues.
4. [ ] Phase 4 - [RC Mail Readiness](../strategies/16.4_RC_MAIL_READINESS.md)
   Rehearse alias-off RC mail readiness on the chosen transport once the Phase 3 notification UX and Browser QA are complete.

## Exit Criteria
- [x] The runaway/spam-prone outbound-mail bug is understood and fixed.
- [x] App-side delivery safeguards are in place and documented.
- [x] Mailgun sendability is restored or an explicit alternate path is chosen.
- [x] Mailgun HTTP API is documented as the chosen MS16 transport and implemented unless a concrete blocking constraint forces a temporary fallback.
- [ ] The remaining notifications/alerts/RC-readiness work ships on top of a trustworthy delivery path.
