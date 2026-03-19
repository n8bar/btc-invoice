# MS14 Phase 3 Browser QA
Date: 2026-03-18

## Scope
- PR `#46`
- MS14 Phase 3 items `3.1` through `3.4`

## Verified
- Saving a proactively flagged wallet key still succeeds and does not hard-block the owner.
- The wallet warning copy stayed gentle and corrective:
  - “We found wallet activity outside CryptoZing.”
  - “Automatic payment tracking is no longer reliable for this wallet account.”
  - “Connect a fresh dedicated account key to keep future invoices on a dedicated receive path.”
- A new invoice created while the wallet was flagged was marked unsupported at creation time.
- An older invoice created before the wallet was flagged remained unflagged.

## Findings
1. The `Unsupported configuration` label near the user menu existed, but it was not clearly actionable during Browser QA. Clicking that label should open the user menu so the repair path is more obvious.
2. No visible red dot surfaced on the `Settings` item in the user menu during Browser QA.
3. No visible red dot surfaced on the `Wallet` tab within `Settings` during Browser QA.
4. The invoice creation screen still allowed save while the wallet was flagged without showing a flagged-state warning or confirmation before save.

## Disposition
- The user-menu unsupported label affordance is being corrected on PR `#46`.
- The Settings and Wallet red-dot repair cues remain open Phase 3 work.
- The invoice-create flagged-state warning/confirmation remains open Phase 3 follow-up work.
