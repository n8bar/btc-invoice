# Post-Payment Onboarding Spec (MS17 Early Scope)

Purpose: define a small second activation flow that helps a signed-in owner handle the first receipt-eligible paid invoice without turning RC into a full tutorial system.

## Scope
- This is onboarding Part 2. Part 1 still covers `connect wallet -> create invoice -> enable share + deliver`.
- Part 2 stays mostly invisible until the owner has a first paid invoice that is eligible for truthful client-receipt follow-up.
- The flow should teach one operator responsibility first: review the paid invoice, then send the client receipt.
- If the first paid invoice is not currently safe for receipt follow-up because the owner needs to correct interpretation first, the flow should suspend instead of pushing the owner toward an untruthful receipt.
- RC scope should rely on contextual guidance copy for edge cases such as ignore, reattribution, and small-balance resolution. RC does not need a fake-invoice tutorial, scenario simulator, or sandbox ledger for those cases.

## Goals
1. Make the first owner-reviewed client receipt feel intentional rather than surprising.
2. Teach the owner why a payment acknowledgment is not the same thing as a client receipt.
3. Keep the first paid-invoice follow-up truthful when correction or reconciliation work is still needed.
4. Reuse the smallest possible UX surface so the product can ship RC without a second large onboarding project.

## Activation Model
1. **Trigger candidate**
   1. Part 2 becomes eligible when the owner has a first user-owned invoice that is `paid`.
   2. The candidate invoice must still need its first client receipt follow-up; if a client receipt was already queued or sent, that invoice should not trigger Part 2.

2. **Receipt-eligible state**
   1. Part 2 should actively prompt only when the first paid invoice is currently receipt-eligible.
   2. `Receipt-eligible` means the app can truthfully encourage the owner to review the invoice and send the client receipt under the current payment state.

3. **Suspend / defer behavior**
   1. If the first paid invoice later proves to need ignore, reattribution, or other payment-review work before a receipt would be truthful, Part 2 should suspend instead of remaining active on that invoice.
   2. Once the blocking review work is cleared, Part 2 may resume on that same invoice or the next eligible paid invoice.

4. **Completion**
   1. Part 2 completes when the owner sends the first reviewed client receipt for an eligible paid invoice.
   2. Owner notices, payment acknowledgments, or other delivery-log activity do not count as completion by themselves.

## Required Owner Understanding
Part 2 should make these points clear in concise language:
- A detected payment acknowledgment is low-information and not the same thing as a receipt.
- A client receipt is a higher-certainty follow-up that should be sent only after the owner reviews the payment state.
- If something looks wrong, the owner should correct the payment interpretation before sending the receipt.
- Small remaining balances should not be hand-waved away automatically; if the invoice is within the allowed threshold, the owner can explicitly choose `Resolve small balance`.
- Overpayments may be intentional tips or accidental extra payments, so the owner should review the invoice context before deciding what to communicate next.

## UX Shape
- Prefer a lightweight prompt, shell, progress strip, or invoice-focused guidance state over a large wizard.
- The prompt should appear where the owner naturally notices it during payment review, such as the dashboard and the eligible invoice show page.
- The CTA should take the owner directly to the review/send-receipt surface for the eligible invoice.
- Guidance should stay contextual and short. Link to the relevant payment-history and correction controls instead of trying to reteach the whole product.
- If the owner is blocked by ignore/reattribution review, the UI should say why the receipt step is paused and point at the relevant correction surface.

## Out of Scope for RC
- Fake invoices or simulated payment scenarios inside the real owner ledger.
- A broad interactive tutorial for every payment edge case.
- Forcing owners through overpayment, underpayment, ignore, reattribution, or small-balance scenarios before they can complete Part 2.
- Building a separate tutorial-only sandbox account model unless we later decide that support/training pressure truly justifies it.

## Relationship to Existing Specs
- Part 1 onboarding remains defined in [`docs/specs/ONBOARD_SPEC.md`](ONBOARD_SPEC.md).
- Receipt review and acknowledgment behavior remain defined in [`docs/specs/NOTIFICATIONS.md`](NOTIFICATIONS.md).
- Ignore/reattribution behavior remains defined in [`docs/specs/PAYMENT_CORRECTIONS.md`](PAYMENT_CORRECTIONS.md).
- Small-balance resolution and overpay/tip handling remain defined in [`docs/specs/PARTIAL_PAYMENTS.md`](PARTIAL_PAYMENTS.md).

## MS17 Intent
- Fold this work into the early part of MS17 rather than creating a standalone milestone.
- Treat this as a small activation/help UX slice that should ship once MS16 leaves the manual-review receipt path stable enough to teach.
- If we later decide to add a simulated tutorial, fake invoices, or broader operator training, that expanded scope should be reconsidered separately instead of being smuggled into RC under this spec.
