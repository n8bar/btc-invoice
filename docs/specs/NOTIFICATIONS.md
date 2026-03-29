# Notifications, Delivery & Alerts Spec

## 1. Spec Scope
### 1. This spec is canonical for the following outbound communication and notifications in behalf of users to their clients:
1. manual invoice send flow
2. paid receipts
3. automated alert triggers
4. shared delivery-log mechanics
5. cooldown expectations and notice-class behavior

## 2. Goals
1. Let invoice owners email a public link plus summary to their client from the app.
2. Enable automatic, non-promissory payment acknowledgments and truthful receipt emails.
3. Support truthful alert emails when an invoice becomes past due or when on-chain payments deviate from the invoice total by more than the defined tolerance, including overpayment, underpayment, and partial-payment events.
4. Keep payment-triggered mail truthful when the underlying payment state is semantically ambiguous, while still acknowledging what it safely can.

## 3. Base Communication Flows
1. **Send Invoice (manual owner action)**
   1. Available when the invoice has a client email and its public link is enabled.
   2. Sending the invoice queues outbound delivery, records the attempt in the delivery log, and issues the invoice out of `draft`.

2. **Payment Acknowledgment + Receipt**
   1. **Detected Payment Acknowledgment**
      1. When the system detects a Bitcoin payment with enough confidence to acknowledge it safely, it should send a low-information client acknowledgment before any reviewed receipt.
      2. This acknowledgment should confirm only what the system can safely say, such as the detected BTC amount, without claiming that the payment has been fully applied to a specific invoice state.
      3. The acknowledgment must remain non-promissory and should not imply that a receipt, refund, or other outcome is guaranteed.
      4. If the payment state is ambiguous, the acknowledgment should stay limited to what the system can safely say and should avoid any certainty about how the payment applies.
      5. When an acknowledgment is tied to a specific detected payment identity, repeated detection of that same payment must not create a second acknowledgment for the same notice class.
   2. **Later Payment Ambiguity**
      1. After an invoice has already received detected on-chain payment activity, later payments to that invoice’s address may still be semantically ambiguous even when the wallet configuration remains supported.
      2. Examples include stale-address reuse and payers intentionally using an older valid invoice address for a newer invoice.
      3. Later-payment ambiguity should narrow the acknowledgment to what the system can safely say rather than suppressing it outright.
   3. **Receipt Follow-Up**
   1. A receipt is a higher-certainty follow-up than an acknowledgment and should only be sent from a truthful reviewed payment state.
   2. If later-payment ambiguity or another ambiguity gate is active, automatic reviewed receipts / paid confirmations must not send until issuer review is complete.
   3. The product must support a clear owner-facing path to send that receipt after any needed review, ignore, or reattribution work.
      1. That path should stay visible from the invoice payment history and from dashboard payment-review surfaces when a paid invoice still lacks a queued or sent client receipt.

3. **Delivery Log**
   1. Outbound invoice communication should be recorded in a shared delivery history for audit and operator review.
   2. That history should cover manual sends, payment acknowledgments, receipts, and automated alerts.
   3. Owners should be able to review delivery outcomes, including queued, sent, skipped, and failed states.

## 4. Automated Alert Triggers
1. **Invoice Paid Notice (Owner)**
   1. When an invoice becomes paid, the invoice issuer should receive a succinct owner notice.
   2. This notice should summarize the paid state, relevant payment details, and link back into the app for follow-up.
   3. The owner paid notice should remain distinct from any client-facing payment acknowledgment or receipt in the delivery history.

2. **Past Due (Owner + Client)**
   1. When an invoice becomes past due and is still not `paid` or `void`, both the invoice issuer and the client should receive past-due reminders.
   2. The owner reminder should communicate that the invoice is overdue, include the outstanding totals, and suggest next steps.
   3. The client reminder should communicate the overdue status, include the outstanding balance and invoice link, and include a short “contact the sender if you already paid” caveat.

3. **Significant Overpayment Alert (Client)**
   1. Triggered when the invoice reflects a significant overpayment (15% threshold for RC).
   2. The client alert should explain that overpayments are treated as gratuities by default and tell the client to contact the sender if the overpayment was accidental.

4. **Significant Underpayment Alert (Client)**
   1. Triggered when the invoice still carries a significant remaining balance after payment activity (15% threshold for RC).
   2. The client alert should neutrally communicate that a balance remains, include the outstanding USD/BTC amounts, and link to the public invoice so the client can settle; where appropriate, it may encourage completing the remaining balance in one payment for convenience.
   3. Fragmented or repeated partial payments should not create a separate repeated-warning alert family; they should continue to use the final underpayment behavior instead.

## 5. Outbound Mail and History
1. Outbound invoice communication should use a shared queued delivery path and shared delivery history so send outcomes remain auditable.
2. The shared delivery history should preserve enough context to identify the invoice, sender/issuer context, recipient(s), communication class, outcome, and timing/error details for each outbound attempt.
3. Public-share links embedded in outbound emails must use the explicitly configured public host for the intended recipient-facing environment.
   1. That public host may differ from the host currently running the app, such as when a development or staging environment is deliberately targeting another deployment.
4. Client-facing payment-triggered notification emails should use explicit paired owner/client delivery classes for RC rather than relying on a generic issuer-copy toggle.
5. Outbound mail capability is a required part of a valid recipient-facing deployment.
   1. Development and test environments may intentionally run without outbound mail, but production-ready deployments must have it configured.
6. The shared delivery path must suppress duplicate or too-recent outbound attempts by notice class and record those suppressed attempts as `skipped` rather than silently dropping them.
7. Outbound idempotency must be enforced at both delivery-intent creation and send execution so double clicks, concurrent processes, queue retries, or duplicate jobs do not produce duplicate outbound mail.
8. Idempotency keys must be derived from stable business intent such as invoice, notice class, normalized recipient, and when applicable payment identity like `txid`, rather than rendered email bytes, provider-added headers, or variable timestamps in the subject/body.
9. The outbound mail path must support an operator-controlled send-disable or circuit-breaker state that records attempted deliveries truthfully while outbound mail is disabled.
10. Queued deliveries must revalidate current invoice truth before sending and mark the delivery `skipped` if the queued notice no longer matches the current recipient, public-share state, or payment state.
11. The delivery history should surface queued, sending, sent, skipped, and failed outcomes.
   1. `sending` is the claimed provider-boundary state used to prevent duplicate job execution from producing a second outbound send while a delivery is already in progress or awaiting operator review after an ambiguous worker failure.
12. The delivery history should use concise, human-friendly labels for communication classes and outcomes.
   1. Manual invoice sends should display as `Invoice email`, not a raw storage key.
   2. Paired owner/client notification rows should keep the audience explicit in the label, such as `Past-due reminder (client)` and `Underpayment alert (owner)`.
   3. While the legacy repeated-partial warning rows still exist in stored history, they should display honestly as `Partial payment warning (client|owner)` rather than being hidden behind renamed copy.
   4. Payment-triggered follow-up should keep the acknowledgment-versus-receipt split visible in history once those rows ship, using labels such as `Payment acknowledgment (client)` for the narrow automatic notice and `Receipt (client)` for the higher-certainty follow-up.
   5. Outcome labels should display as `Queued`, `Sending`, `Sent`, `Skipped`, and `Failed`.
13. Outbound mail copy should stay concise and actionable.
