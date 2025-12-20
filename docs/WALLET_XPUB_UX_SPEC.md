# Wallet / XPUB UX Spec (MS13 — UX ToDo #7)

Purpose: make wallet setup mainnet-first and approachable for non-technical users while keeping derivation safety intact. Applies to `/wallet/settings` primary wallet + additional wallets.

## Goals
- One-step setup: network derives from `WALLET_NETWORK` (`mainnet`, `testnet4`, or `testnet3`); user only pastes a wallet account key (BIP84 xpub/zpub/vpub/tpub).
- Clear, confidence-building guidance with inline “show me how” steps and wallet-specific hints.
- Fast, calm validation that preserves user input and avoids layout jumps.
- Friendly error recovery and explicit testnet-only cues when relevant; no network noise on mainnet.
- Apply the UX guardrails in [`docs/UX_GUARDRAILS.md`](UX_GUARDRAILS.md) for all wallet UX work (Nielsen/WCAG + project non‑negotiables).

## Completed Tasks
1. Mainnet-first UI: env-driven network only, no mainnet badge/copy, testnet-only helper text, and server-side prefix validation to reject mismatched networks (primary + additional wallets).
2. Guardrail polish on existing wallet form:
   - Reserved vertical space for helper/success/error text under the field to avoid layout shift.
   - Validation failures refocus the wallet key field; primary CTA stays enabled after errors.
   - Labels/copy use plain language while format examples live in helper text.
3. Inline helper/accordion:
   - Added the "Where do I find this?" accordion with 4-step guidance, wallet badges, and seed-warning copy.
4. Derive-test flow:
   - Blur/save validation with inline spinner, success check + sample address preview, friendly error copy, and a re-run control.
5. Additional wallets:
   - Additional wallets surface the same helper/validation states and inherit the configured network; mixed-network keys are rejected.
6. Tests:
   - Coverage for helper visibility, validation preview endpoint, invalid-key errors preserving input, and additional-wallet network enforcement.

## ToDo
- None.

## UI & Interaction
- Layout: keep the primary wallet form above the fold on laptop screens; reserve space under the field for helper/validation so the layout does not shift.
- Field: label as “Wallet account key (xpub/zpub/vpub/tpub)” with a sub-label “Paste the account-level public key from your wallet. Never paste a seed phrase.”
- Helper links:
  - Inline link: “Where do I find this?” opens a small accordion.
  - Accordion content (bullet steps, max 4 steps):
    1) Open your wallet and choose the account you want payments to land in.
    2) Go to Receive (or Account details) → Advanced/export.
    3) Copy the account public key (often labeled xpub/zpub/vpub/tpub). Do not copy a single address.
    4) Paste here. You can verify below before saving.
  - Provide wallet badges with concise notes, e.g., “Ledger Live: Account → … → Account extended public key,” “Trezor Suite: Accounts → Receive → Show public key,” and mobile-friendly cues like “Blockstream Green (iOS/Android): Account → three dots → Export xpub” and “BlueWallet/Nunchuk (iOS/Android): Account → More/Manage → Export xpub.”
- Testnet cue: if `WALLET_NETWORK` is not mainnet, show a small helper above the field: “Testnet (for testing only). Real payments require mainnet.” No badge/no copy on mainnet.
- Additional wallets section mirrors the primary form (same helper, same validation) and inherits the configured network; disallow mixed networks.
- CTA area: primary “Save wallet” button stays enabled after errors; secondary “Re-run validation” link/button near the helper for retry.

## Validation & States
- When user blurs or clicks “Save,” run the derive test once.
- Success: inline green check with text “Address validated for this key” and show the derived sample address in monospace; keep helper text visible.
- Invalid key/parse failure: red helper “That key doesn’t look right. Check you copied the full account public key (no spaces/line breaks).” Keep the input intact and focus the field.
- Derivation failure (script error/network mismatch): “We couldn’t read that key. Confirm it matches the configured network and try again.” Preserve input; allow retry.
- Empty submission: “Please paste your wallet account key.”
- Loading: small inline spinner next to the helper, no page-level overlay; avoid shifting the CTA.

## Copy & Tone
- Avoid jargon in headings; use “Wallet settings” and “Wallet account key.”
- Safety reminder: “This key can receive only. Never share or paste your seed phrase.”
- Support link: small “Need help?” linking to onboarding doc section.

## Accessibility & Responsiveness
- Visible focus rings on input, helper links, and buttons in light/dark themes.
- Accordion is keyboard navigable; content is readable on mobile with no horizontal scroll.
- Reserve helper space to prevent layout jumps when showing validation.

## Testing Notes
- Feature tests:
  - Mainnet: no testnet helper appears.
  - Testnet: helper text renders; network is not selectable.
  - Invalid xpub: inline error appears, input preserved, submit stays enabled.
  - Successful validation: success message + sample address render.
  - Additional wallet form mirrors behavior and rejects mixed-network adds.
- View/Blade coverage can use snapshot-style assertions for helper/accordion visibility.

## Definition of Done
- Wallet settings implements the above layout, helper copy, validation states, and testnet-only cue.
- Additional wallets share the same UX patterns and respect the configured network.
- No network selector remains; env-driven network is authoritative.
- Docs updated: onboarding walkthrough/quick start screenshots later; PLAN/CHANGELOG/UX spec link updated with this spec reference.
