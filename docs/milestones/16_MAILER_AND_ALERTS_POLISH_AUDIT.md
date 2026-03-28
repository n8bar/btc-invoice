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
- Active focus: **Milestone doc restructure + phase breakout**
- Current objective: reorganize this milestone doc back toward milestone-level summary only, break Phase 1 through Phase 3 into their own execution docs, and resolve, relocate, or deliberately discard the current open-question / brain-dump sprawl before broader MS16 implementation proceeds.
- Primary surfaces: [`docs/specs/NOTIFICATIONS.md`](../specs/NOTIFICATIONS.md), current mailer/delivery implementation, and Mailgun account state.

## Phase Rollup
1. [ ] Phase 1 - Mailer Safety + Spam-Bug Triage
   Identify the runaway-send failure mode, define the app-side safeguards required before broader mailer work, and decide whether any notification behaviors need owner confirmation/validation before outbound mail can safely send.
2. [ ] Phase 2 - Provider Recovery + Transport Decision
   Determine whether Mailgun is currently blocking or throttling us, decide what recovery action is required, and choose whether MS16 stays on SMTP or moves to the Mailgun HTTP API.
3. [ ] Phase 3 - Notifications Polish + Audit
   Resume the broader notifications/alerts polish once the delivery path is trustworthy again, including any later-payment validation work, delivery-log polish, the issuer-copy toggle for client-facing notification emails (default on for RC), and related alert-behavior hardening.

## Open Scope Questions
- During MS14 Browser QA we found evidence that some payment-triggered outgoing mail may need owner confirmation/validation first to catch ignored, reattributed, or otherwise semantically ambiguous payment states. MS16 should decide whether that remains a narrow later-payment safeguard or becomes a broader notification rule.
- The later-payment owner-validation gate belongs to MS16 follow-up work, not to the earlier MS14 reattribution tooling itself. Keep that sequencing explicit when Phase 3 gets drafted so we do not back-assign the guardrail to the wrong milestone.
- The spec no longer hardcodes a blanket later-payment validation gate. Phase 3 should decide whether later-payment owner validation is needed at all, and if so:
  - whether it applies only to higher-certainty follow-up mail while still allowing the limited acknowledgment path
  - which outbound classes it covers
  - whether manual sends and past-due reminders stay outside that safeguard
- If MS16 introduces an automatic low-information payment acknowledgment before any owner-reviewed receipt, keep the copy deliberately narrow and non-promissory. Current candidate wording:
  - `A Bitcoin payment of 0.00123456 BTC was detected.`
  - `No action is needed right now.`
  - `The invoice issuer has been notified to review it promptly.`
- If MS16 adopts that acknowledgment path, the operator side must actually support the last line: the invoice issuer needs a prompt, visible review/receipt CTA in the dashboard and invoice payment history so the acknowledgment does not become hollow reassurance.
- Past-due reminder cadence, cooldown spacing, and exact delivery-history representation are deferred MS16 details. The spec keeps only the owner/client reminder behavior; Phase 3 should decide the actual schedule, resend interval, and how distinct owner/client reminder entries are recorded.
- The spec no longer treats repeated partial payments as a distinct client-facing alert family. If MS16 still wants to react specially to multiple partial payments, treat that as an owner-side or operational signal to design later rather than as a separate client alert category.
- Temporary catch-all aliasing is not a spec-level rule, but MS16 strategy work should still account for it explicitly. The later phase doc should decide when aliasing remains useful for safe testing, when it becomes misleading for delivery validation, and when it must be disabled before real-recipient traffic or RC rollout.

## Leftover Implementation Brain-Dump
These notes were moved out of `docs/specs/NOTIFICATIONS.md` because they are implementation-shaped leftovers, not approved hard requirements. Keep them only as MS16 reference material until the phase docs decide which ideas, if any, survive.

- Candidate base communication classes:
  - `App\Mail\InvoiceReadyMail`
  - `App\Mail\InvoicePaidReceipt`
- Candidate additional mailables:
  - `InvoiceOwnerPaidNoticeMail`
  - `InvoicePastDueOwnerMail`
  - `InvoicePastDueClientMail`
  - `InvoiceOverpaymentClientMail`
  - `InvoiceUnderpaymentClientMail`
  - optional distinct underpayment-owner copy instead of relying only on issuer copies/CC
  - previously brainstormed partial-payment warning mailables/FYIs if that idea ever returns in a narrower owner-side form
- Shared Blade partials may still be a good way to keep invoice header/footer branding aligned across email types.
- The current manual-send entry point is `POST /invoices/{invoice}/deliver`; if that remains the long-term path, Phase 3 should preserve ownership, client-email, and public-link validation before enqueueing work.
- A lightweight notification-intent or dedupe service may still be useful if watcher/manual-adjustment flows need shared send gating.
- Persisted last-alert timestamps on `invoices` may still be one way to prevent repeated sends within a cooldown window.
- Scheduler ideas worth revisiting later:
  - a dedicated past-due alert command run on a schedule
  - immediate over/under-payment alert triggering from payment-detection flows

## Exit Criteria
- [ ] The runaway/spam-prone outbound-mail bug is understood and fixed.
- [ ] App-side delivery safeguards are in place and documented.
- [ ] Mailgun sendability is restored or an explicit alternate path is chosen.
- [ ] The transport decision for MS16 (`SMTP` vs `Mailgun HTTP API`) is documented and implemented if needed.
- [ ] The remaining notifications/alerts polish ships on top of a trustworthy delivery path.
