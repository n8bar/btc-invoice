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
1. The invoice creation screen still allowed save while the wallet was flagged without showing a flagged-state warning or confirmation before save.

## Disposition
- The repair-path affordance and red-dot cues are corrected on PR `#46`.
- The invoice-create flagged-state warning/confirmation remains open Phase 3 follow-up work.

## Follow-up Verification
- After the repair-path UI fixes and asset rebuild on 2026-03-18, Browser QA confirmed:
  - clicking the `Unsupported configuration` pill opens the user menu
  - the red dot is visible on `Settings`
  - the red dot is visible on the `Wallet` tab
  - the repair path leads cleanly to the wallet warning block
