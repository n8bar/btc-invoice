# Finding 1: Shared xpub/account causes false on-chain payment attribution

Date: 2026-02-25

## Summary

A brand new invoice (`#14`, user `Test One`) appeared as `paid` immediately after creation.

Root cause: the user connected an account xpub that is also being used by another wallet app. CryptoZing derived an address in the same namespace, and that address already had on-chain payments associated with it. Because on-chain payment detection is address-based, the invoice inherited unrelated payment history.

## What We Observed

- Invoice `#14` was created as a new invoice but showed `status = paid`.
- The invoice had confirmed payments already attached, dated before the invoice was created.
- This was not a UI status-button bug.
- This was not a wallet-key validation/derivation failure.

## Design Constraint (Key Conclusion)

If CryptoZing must reliably auto-detect and attribute on-chain payments, then the app needs an exclusive derivation namespace.

With xpub-only integration, that means a dedicated account xpub (dedicated account) for CryptoZing.

Using the same account xpub in other apps/wallet workflows can cause address reuse/collision and corrupt invoice payment attribution.

## Mitigations Considered (and Why They Are Not Sufficient)

- Time-gating prior activity:
  - Not sufficient. Another app can use the same address later and still pollute invoice history.
- Auto-skip "used" addresses until an empty one is found:
  - Not sufficient. Future collisions remain possible if the account is shared.
- Random starting derivation index:
  - Reduces immediate collision probability, but does not guarantee exclusivity.
  - Risks wallet discoverability/gap-limit issues in common wallet apps.
- QR metadata / invoice identifier in QR:
  - Not reliable for on-chain attribution with standard Bitcoin wallets.
- App-controlled wallet + forwarding/sweeping:
  - Technically possible, but this becomes a custodial payment-processor design (major scope and risk increase).

## Product Decision (Locked In)

- On-chain payment detection remains a fundamental feature of CryptoZing.
- CryptoZing will require/support the expectation of a dedicated account xpub for reliable automatic payment attribution.
- Users may technically view the same account in other wallet apps and spend/send funds from it, but they should not use that same account for additional receives/address generation outside CryptoZing if they want reliable invoice tracking.

## Mitigation Direction

1. Wallet Settings page UX/copy:
   - Add clear guidance that the connected account must be dedicated to CryptoZing for reliable auto-tracking.
   - Explain that reusing the same account elsewhere can cause false invoice payment detection.
   - Clarify that the account can still be viewed/managed in other wallet apps and used to send/spend funds, but should not be used for other receives/address generation.

2. Recovery/correction tooling for stubborn/shared-account usage:
   - Add a way to void/ignore wrongly attributed on-chain payments.
   - Include strong warning copy when using this action (encourage fixing wallet setup instead of relying on manual corrections).
   - Treat this as a corrective escape hatch, not the recommended workflow.

## Follow-Up Scope (To Spec Later)

- Update wallet-settings UX/spec text to reflect dedicated-account requirement.
- Add payment-void/correction UX and guardrails.
- Decide whether onboarding should hard-block progression until the user acknowledges the dedicated-account requirement.
