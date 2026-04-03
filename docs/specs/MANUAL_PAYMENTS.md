# Manual (Off-Chain) Payment Recording

Spec for owner-facing tooling to record payments received outside the Bitcoin network.

## Problem
Issuers occasionally receive payment via wire transfer, cash, check, or other off-chain methods. Without a way to record these against an invoice, the ledger shows a false outstanding balance and the invoice can never reach `paid` status without being voided and recreated.

## Scope
- Add an owner-facing "Record manual payment" form on the invoice page, alongside existing adjustment tools.
- Fields: amount (USD), optional reference/note (e.g. "Wire transfer ref #12345"), date received.
- On save: create an `InvoicePayment` record flagged as manual (no on-chain txid), recalculate the payment ledger, and transition the invoice to `paid` if the balance is fully closed.
- Manual payment rows must be clearly distinguishable from on-chain payments in the payment history UI.
- If a manual payment closes the balance, the receipt panel becomes available as with any other paid state.
- Do not allow manual payments on voided invoices.
- The existing small-balance resolver and reattribution tools remain unchanged.

## Out of Scope
- Editing or deleting recorded manual payments (post-RC, covered by backlog item 14).
- Multi-currency off-chain payments; USD only for RC.
