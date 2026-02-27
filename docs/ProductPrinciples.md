# Product Principles

Internal product principles for CryptoZing. These guide UX, engineering tradeoffs, and operational decisions when handling invoices and on-chain payment tracking.

## Core Principles

1. Respect property rights and access to funds.
   - Money is not sacred, but a person's right to their property is.
   - For this product, that makes money sacred-adjacent: we treat access, ownership, and attribution with high care.

2. Never trap funds.
   - Product convenience must never create a situation where users lose access to or control over their money.

3. Never create ambiguity about ownership.
   - If the app cannot confidently attribute a payment, the UI and system behavior must reflect that uncertainty.

4. Never auto-assume correctness when attribution is uncertain.
   - Automatic payment detection is valuable, but it must not silently misattribute funds.

5. Make correction paths explicit and reversible where possible.
   - When users make setup mistakes (for example, shared-account xpub usage), the app should provide a clear recovery path.

6. Keep UX honest about product constraints.
   - If reliable on-chain attribution requires a dedicated account xpub/derivation namespace, the app should say so plainly.

7. Prefer minimal, high-leverage safeguards before operational complexity.
   - Add the smallest effective protections first.
   - Defer heavy support/process overhead until real adoption requires it.

## Practical Implications (Current)

- On-chain payment detection is a fundamental CryptoZing feature.
- Reliable automatic attribution requires a dedicated account xpub (dedicated derivation namespace).
- Users may view/manage the account in other wallets and spend/send from it.
- Users should not use that same account for additional receives/address generation outside CryptoZing if they want reliable invoice tracking.
- The app should provide corrective tooling for wrongly attributed payments, while clearly discouraging reliance on that workflow.
