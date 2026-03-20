# Wallet / XPUB UX Spec (MS13 — UX ToDo #7)

Purpose: make wallet setup mainnet-first and approachable for non-technical users while keeping derivation safety intact. Applies to the primary wallet on `/wallet/settings`; additional wallets UI is deferred to post-RC until multi-wallet selection is in scope.

## Goals
- One-step setup: network derives from `WALLET_NETWORK` (`mainnet`, `testnet4`, or `testnet3`); user only pastes a wallet account key (BIP84 xpub/zpub/vpub/tpub).
- Clear, confidence-building guidance with inline “show me how” steps and wallet-specific hints.
- Fast, calm validation that preserves user input and avoids layout jumps.
- Friendly error recovery and explicit testnet-only cues when relevant; no network noise on mainnet.
- Apply the UX guardrails in [`docs/UX_GUARDRAILS.md`](../UX_GUARDRAILS.md) for all wallet UX work (Nielsen/WCAG + project non-negotiables).

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
5. Tests:
   - Coverage for helper visibility, validation preview endpoint, and invalid-key errors preserving input.

## Current Status
- The MS13 wallet-settings baseline in this spec is shipped.
- MS14 wallet settings now explain the dedicated receiving-account requirement directly in the form intro copy.
- MS14 wallet warning copy and invoice unsupported-state visibility are shipped.
- MS14 navigation repair-path indicators are now working end-to-end after Browser QA follow-up on 2026-03-18.
- MS14 invoice creation now shows its own unsupported-state warning and uses a `Create Unsupported Invoice` primary CTA while the wallet is flagged.
- MS14 onboarding now reinforces the dedicated receiving-account requirement and links to a matching Helpful Notes anchor.
- The Helpful Notes guidance for dedicated receiving accounts and unsupported configuration is shipped on the public `Helpful Notes` page.
- MS14 wallet settings now link directly from the dedicated-account guidance block to the matching Helpful Notes anchor and emit a support-safe save log after that guidance is shown.

## Deferred (post-RC)
- Additional wallets UI and multi-wallet selection (backend storage remains; UI will return once the selector is in scope). Tracked in `docs/BACKLOG.md`.

## UI & Interaction
- Layout: keep the primary wallet form above the fold on laptop screens; reserve space under the field for helper/validation so the layout does not shift.
- Wallet settings should include a static guidance block near the top of the form that:
  - says CryptoZing expects a dedicated account key (`xpub` / `zpub` / `vpub` / `tpub`) for invoice receives
  - warns that reusing that same account for receives elsewhere can cause false payment attribution
  - clarifies that viewing balances or spending from that account elsewhere is fine
  - links directly to the matching Helpful Notes dedicated receiving-account explainer
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
- Additional wallets UI is deferred post-RC; when re-enabled, mirror the primary form (same helper/validation) and disallow mixed networks.
- CTA area: primary “Save wallet” button stays enabled after errors; secondary “Re-run validation” link/button near the helper for retry.

## Validation & States
- When user blurs or clicks “Save,” run the derive test once.
- Success: inline green check with text “Address validated for this key” and show the derived sample address in monospace; keep helper text visible.
- Invalid key/parse failure: red helper “That key doesn’t look right. Check you copied the full account public key (no spaces/line breaks).” Keep the input intact and focus the field.
- Derivation failure (script error/network mismatch): “We couldn’t read that key. Confirm it matches the configured network and try again.” Preserve input; allow retry.
- Empty submission: “Please paste your wallet account key.”
- Loading: small inline spinner next to the helper, no page-level overlay; avoid shifting the CTA.

## MS14 Follow-On Wallet Risk UX
- When CryptoZing detects wallet activity outside its dedicated receive flow, the system may internally mark the wallet as an `unsupported configuration`.
- User-facing copy should stay gentle and corrective, not punitive. Preferred direction:
  - “We found wallet activity outside CryptoZing.”
  - “Automatic payment tracking is no longer reliable for this wallet account.”
  - “To keep using automatic tracking, connect a new dedicated wallet account key.”
- Spending from the same wallet elsewhere is not the warning trigger by itself; the warning is about outside receive activity or collision evidence in the same account namespace.
- Red attention UI should appear only when the wallet is actually flagged unsupported:
  - attention-grabbing label near the user menu
  - red dot on the Settings nav item
  - red dot on the Wallet settings tab
  - red warning near the wallet account key field
- Invoice creation should also warn inline when the wallet is flagged unsupported and use a more explicit primary action label such as `Create Unsupported Invoice` rather than a neutral save label.
- The wallet settings warning should explain that unrelated outside wallet activity can lead to mistracked funds and other unreliable automatic attribution behavior, and that the recommended fix is to connect a new dedicated account key.
- Saving wallet settings after the dedicated-account guidance is shown should emit a support/debug log entry with safe context only (user, wallet setting, flow surface, unsupported-state flags), never the wallet key itself.
- Invoices created while the wallet is flagged unsupported should be marked unsupported at creation time.
- Existing invoices must not be bulk retroactively marked unsupported. An existing invoice may be marked unsupported only when invoice-specific evidence implicates that invoice.
- Publish a matching Helpful Notes article in plain language that explains:
  - why CryptoZing is watch-only
  - why automatic payment tracking needs a dedicated receiving account key
  - why spending elsewhere is fine but receiving elsewhere breaks attribution
  - what unsupported configuration means
  - how to fix the problem by connecting a fresh dedicated account key
  - that using separate receive and spend apps or accounts is the safest recommended pattern, without making separate apps a hard product requirement

## Copy & Tone
- Avoid jargon in headings; use “Wallet settings” and “Wallet account key.”
- Safety reminder: “This key can receive only. Never share or paste your seed phrase.”
- Support link: small “Need help?” linking to onboarding doc section.

## Accessibility & Responsiveness
- Visible focus rings on input, helper links, and buttons in light/dark themes.
- Accordion is keyboard navigable; content is readable on mobile with no horizontal scroll.
- Reserve helper space to prevent layout jumps when showing validation.
- The wallet key field should describe the dedicated-account guidance, helper copy, and validation-feedback region so focus/error handling stays understandable for keyboard and assistive-tech users.

## Testing Notes
- Feature tests:
  - Mainnet: no testnet helper appears.
  - Testnet: helper text renders; network is not selectable.
  - Invalid xpub: inline error appears, input preserved, submit stays enabled.
  - Successful validation: success message + sample address render.
- Coverage now includes:
  - proactive unsupported-state detection surfacing the red warning/UI indicators only when the wallet is actually flagged
  - evidence-triggered unsupported-state behavior for an implicated invoice
  - invoices created while the wallet is flagged unsupported inheriting that flag
  - previously existing invoices remaining unflagged unless invoice-specific evidence marks them
  - the Helpful Notes article surfacing the same dedicated-receive guidance in plain language
  - the wallet-settings dedicated-account help link
  - wallet-settings save-log emission with support-safe context
- Additional wallet enforcement remains covered via request validation while the UI is deferred.
- View/Blade coverage can use snapshot-style assertions for helper/accordion visibility.

## Definition of Done
- Wallet settings implements the above layout, helper copy, validation states, and testnet-only cue.
- Additional wallets share the same UX patterns and respect the configured network.
- No network selector remains; env-driven network is authoritative.
- Docs updated: contributor walkthrough/quick start screenshots later; PLAN/CHANGELOG/UX spec link updated with this spec reference.
