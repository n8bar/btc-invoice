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
      1. When the system detects a Bitcoin payment with enough confidence to acknowledge it safely, it may send a low-information client acknowledgment.
      2. This acknowledgment should confirm only what the system can safely say, such as the detected BTC amount, without claiming that the payment has been fully applied to a specific invoice state.
      3. The acknowledgment must remain non-promissory and should not imply that a receipt, refund, or other outcome is guaranteed.
      4. If the payment state is ambiguous, the acknowledgment should stay limited to what the system can safely say and should avoid any certainty about how the payment applies.
   2. **Later Payment Ambiguity**
      1. After an invoice has already received detected on-chain payment activity, later payments to that invoice’s address may still be semantically ambiguous even when the wallet configuration remains supported.
      2. Examples include stale-address reuse and payers intentionally using an older valid invoice address for a newer invoice.
      3. Later-payment ambiguity should narrow the acknowledgment to what the system can safely say rather than suppressing it outright.
   3. **Receipt Follow-Up**
      1. A receipt is a higher-certainty follow-up than an acknowledgment and should only be sent from a truthful reviewed payment state.
      2. The product must support a clear owner-facing path to send that receipt after any needed review, ignore, or reattribution work.

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

## 5. Outbound Mail and History
1. Outbound invoice communication should use a shared queued delivery path and shared delivery history so send outcomes remain auditable.
2. The shared delivery history should preserve enough context to identify the invoice, sender/issuer context, recipient(s), communication class, outcome, and timing/error details for each outbound attempt.
3. Public-share links embedded in outbound emails must use the explicitly configured public host for the intended recipient-facing environment.
   1. That public host may differ from the host currently running the app, such as when a development or staging environment is deliberately targeting another deployment.
4. Client-facing notification emails should also copy the invoice issuer by default, with issuer-level control over that behavior.
5. Outbound mail capability is a required part of a valid recipient-facing deployment.
   1. Development and test environments may intentionally run without outbound mail, but production-ready deployments must have it configured.
6. The delivery history should surface queued, sent, skipped, and failed outcomes.
7. The delivery history should use concise, human-friendly labels for communication classes and outcomes.

## 6. Mailables, Routes, and Jobs
1. Base communication classes:
   1. `App\Mail\InvoiceReadyMail`
   2. `App\Mail\InvoicePaidReceipt`
2. New mailable classes:
   1. `InvoiceOwnerPaidNoticeMail`
   2. `InvoicePastDueOwnerMail`
   3. `InvoicePastDueClientMail`
   4. `InvoiceOverpaymentClientMail`
   5. `InvoiceUnderpaymentClientMail`
   6. (Optional) `InvoiceUnderpaymentOwnerMail` if we want a distinct copy instead of CCing.
   7. `InvoicePartialPaymentWarningClientMail` (plus owner FYI mail/CC)
3. Shared Blade partials should keep invoice header/footer branding aligned across email types.
4. `POST /invoices/{invoice}/deliver` remains the base manual-send endpoint and must validate ownership, client email presence, and public-link availability before enqueueing work.
5. Add a lightweight service that raises “notification intents” from watcher/manual-adjustment flows and deduplicates sends (e.g., don’t send multiple overpay emails for the same invoice unless the percentage keeps climbing and a configured interval has passed).
6. Persist last-alert timestamps on `invoices` (columns such as `last_overpayment_alert_at`, `last_underpayment_alert_at`, `last_past_due_alert_at`) to prevent repeated sends within 24h.
7. Scheduler additions:
   1. Extend the existing `scheduler` service to run a new artisan command (`invoices:send-past-due-alerts`) nightly.
   2. Reuse `wallet:watch-payments` (or hook into `InvoicePaymentDetector`) to trigger over/under payment emails immediately after each detection.

## 7. Copy Guidelines
1. Keep subjects/actionable text short (<=60 chars). Example subjects:
   1. Manual send: `Invoice INV-1042 is ready`
   2. Client receipt: `Receipt for Invoice INV-1042`
   3. Owner paid: `Invoice INV-1042 paid`
   4. Past due client: `Reminder: Invoice INV-1042 is past due`
   5. Overpayment client: `Invoice INV-1042 was overpaid`
   6. Underpayment client: `Invoice INV-1042 has a balance due`
2. Client overpayment body must include: “Overpayments are treated as gratuities by default; please notify the sender if this was a mistake.”
3. Underpayment emails must include outstanding USD/BTC amounts and the public link CTA.

## 8. Testing
1. Feature tests covering:
   1. manual send flow queues work and writes delivery-log entries
   2. failure paths record delivery errors
   3. `InvoicePaid` dispatch triggers owner notice plus client receipt exactly once
   4. automated alert classes respect cooldown and delivery-log rules
   5. semantically ambiguous payment-triggered cases keep outbound communication truthful
2. Optional Blade/mail snapshot tests can be used for the invoice-ready and receipt mailables.

## 9. Open Questions
1. Retry strategy: keep one `invoice_deliveries` row per job fire vs. a single row with status updates. Current direction: one row per job fire for audit clarity.

## 10. Future Enhancements
1. After base implementation, consider allowing owners to configure alert thresholds per profile (default 15%).
2. Once we add Slack/webhook integrations, mirror these events there for ops teams.
