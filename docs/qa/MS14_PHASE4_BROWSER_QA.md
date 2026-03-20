# MS14 Phase 4 Browser QA
Date: 2026-03-19

## Scope
- Branch `codex/ms14-phase4-ux-hardening`
- MS14 Phase 4 items `4.1` through `4.5`

## Verified
- Wallet settings dedicated-account guidance is clear, links to the matching Helpful Notes explainer, and now explicitly states that the owner's wallet app remains the normal place to view balances and spend because CryptoZing is watch-only.
- Wallet settings uses generic key labels plus an env-aware “Usually starts with …” hint, and the mainnet wallet-settings surface does not expose `vpub` or `tpub`.
- The getting-started wallet step keeps the dedicated-account warning understandable and matches the stronger watch-only wording.
- The Helpful Notes dedicated receiving-account article matches the in-app warning language and reads cleanly for less technical users.

## Findings
- Initial Browser QA found two copy issues before final signoff:
  - “Viewing balances or spending from that account elsewhere is fine.” was too weak because external wallet use is the normal required workflow.
  - Wallet-settings static copy exposed testnet prefixes on a mainnet surface.

## Disposition
- The copy follow-up shipped on 2026-03-19:
  - wallet settings now says the owner's wallet app remains the normal place to view balances and spend
  - onboarding uses the same stronger watch-only framing
  - Helpful Notes now explains that CryptoZing only watches for invoice receives while the wallet app handles balances, sweeping, and spending
  - wallet settings uses generic key labels plus an env-aware prefix hint, and mainnet wallet settings no longer exposes `vpub` / `tpub`

## Signoff
- Browser QA accepted after the copy follow-up and the approved reading of the Phase 4 surfaces.
