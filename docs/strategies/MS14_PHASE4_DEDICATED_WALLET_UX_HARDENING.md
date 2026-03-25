# MS14 Phase 4 Strategy - Dedicated-Wallet UX Hardening

Status: Completed.
Parent milestone doc: [`docs/milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md`](../milestones/MS14_PAYMENT_ATTRIBUTION_HARDENING.md)

## 4.1 Update wallet settings copy
1. [x] Update wallet settings copy to explicitly state that CryptoZing expects a dedicated account xpub for receives.
2. [x] Explain that sharing the same account for receives elsewhere can cause false payment attribution.
3. [x] Clarify that viewing or spending from that account elsewhere is fine.
   - Current implementation on 2026-03-19: `/wallet/settings` now includes a dedicated receiving-account guidance block in the form intro that states the dedicated-account requirement, warns about wrong-invoice attribution when the same account receives elsewhere, uses a generic wallet-account-key label, keeps its concrete prefix hint env-aware (`xpub/zpub` on mainnet, `vpub/tpub` on testnet), and explicitly says the owner's wallet app remains the normal place to view balances and spend because CryptoZing is watch-only.

## 4.2 Reinforce the dedicated-account requirement in onboarding
1. [x] Add onboarding reinforcement in the wallet step with a concise warning block and link to the Helpful Notes anchor.
2. [x] Add an explicit acknowledgment checkbox only if Browser QA shows the warning copy is being ignored.
   - Current implementation on 2026-03-19: the getting-started wallet step now carries a dedicated-account warning block plus a direct link to the dedicated receiving-account Helpful Notes anchor, and it now explicitly says the owner will still use their wallet app to view balances and spend from that account. No acknowledgment checkbox shipped because Browser QA has not shown a need for one.

## 4.3 Publish a Helpful Notes explainer for less technical users
1. [x] Add a public Helpful Notes article that explains CryptoZing's watch-only model in plain language.
2. [x] Explain why automatic payment tracking needs a dedicated receiving account key.
3. [x] Explain that spending elsewhere is fine, but receiving elsewhere with the same account key makes automatic attribution unreliable.
4. [x] Explain what unsupported configuration means and why the recommended fix is to connect a fresh dedicated account key.
5. [x] Recommend separate receive and spend apps or accounts as the safest pattern without making separate apps a hard product requirement.
   - Current implementation on 2026-03-19: `Helpful Notes` now includes a dedicated receiving-account article that explains the receive-only requirement, what breaks tracking, what unsupported configuration means, how to fix it with a fresh dedicated key, and that the owner's wallet app remains the normal place to view balances, sweep, and spend because CryptoZing is watch-only. The existing import guidance keeps mainnet-style `xpub/zpub` examples in plain language.

## 4.4 Keep dedicated-wallet guidance usable and traceable
1. [x] Apply `docs/UX_GUARDRAILS.md` so dedicated-wallet guidance does not introduce layout shift.
2. [x] Preserve wallet input on validation failures.
3. [x] Keep keyboard and focus behavior sane for any new guidance controls.
4. [x] Add event or log entries when users save wallet settings after seeing dedicated-account guidance if support/debug traceability proves necessary.
   - Current implementation on 2026-03-19: `/wallet/settings` keeps its reserved helper/error space, preserves pasted wallet input on validation failures, ties the wallet key textarea to the guidance/helper/feedback regions for sane focus and screen-reader context, links the guidance block directly to the dedicated receiving-account Helpful Notes anchor, and emits `wallet.settings.saved_with_dedicated_guidance` without logging the wallet key itself.

## 4.5 Verify Phase 4
Automated / command verification:
1. [x] Run `./vendor/bin/sail artisan test` at minimum for merge-ready Phase 4 work.
   - Current result on 2026-03-19: `236 passed`.
2. [x] Add or expand automated coverage for wallet-settings copy/flow changes, any onboarding reinforcement that ships, and any Helpful Notes linkage or rendering that ships with this phase.
   - Current result on 2026-03-19: coverage now includes the wallet-settings dedicated-account help link, the Helpful Notes dedicated receiving-account explainer rendering, the onboarding help link, and wallet-settings save-log emission.
3. [x] Confirm any telemetry or logging added for guidance acknowledgment is emitted as expected.
   - Current result on 2026-03-19: feature coverage now asserts `wallet.settings.saved_with_dedicated_guidance` is emitted with support-safe context only.

Browser QA:
4. [x] Verify dedicated-account warning clarity on wallet settings.
   - Current result on 2026-03-19: Browser QA confirmed the warning is clear after the watch-only copy follow-up and that wallet settings now uses generic key labels plus an env-aware “Usually starts with …” hint without exposing `vpub/tpub` on mainnet.
5. [x] Verify onboarding reinforcement is understandable and does not introduce layout shift or focus regressions.
   - Current result on 2026-03-19: Browser QA confirmed the getting-started wallet warning remains understandable after the copy follow-up and no layout-shift or focus regressions were called out.
6. [x] Verify the Helpful Notes explainer is understandable to a less technical audience and matches the in-app warning language.
   - Current result on 2026-03-19: Browser QA confirmed the Helpful Notes dedicated receiving-account explainer matches the in-app warning language and reads clearly with the stronger watch-only explanation.
