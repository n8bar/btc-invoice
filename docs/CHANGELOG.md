# Project Changelog

| Date (America/Denver) | Change | Notes |
|-----------------------|--------|-------|
| 2026-02-19 | UX Task 10 strategy documented | Expanded `docs/UX_OVERHAUL_SPEC.md` Task 10 with implementation lock, acceptance checklist, and verification plan; mirrored PLAN Item 13 Task 10 with an explicit pointer to the locked checklist before code implementation. |
| 2026-02-19 | UX Task 9 checked off | Completed the invoices/clients mobile sanity sweep (no horizontal overflow + action wrapping) and applied a universal 4px base `<main>` gutter (`px-1`) across app/public/guest layouts; marked UX Overhaul Task 9 complete in PLAN/spec docs. |
| 2026-02-19 | Wallet key storage length hotfix | Added migration `2026_02_19_192045_widen_wallet_xpub_columns` to widen `wallet_settings.bip84_xpub` + `user_wallet_accounts.bip84_xpub` to `TEXT` so encrypted wallet keys are saved without truncation; added regression coverage in `tests/Feature/UserSettingsTest.php` for realistic-length testnet keys on both primary and additional wallet flows. |
| 2026-02-19 | UX checklist dark-mode sweep checked off | Marked the Task 9 human-eyes dark-mode readability sweep as complete in `docs/UX_OVERHAUL_SPEC.md`; mobile sanity sweep remains open. |
| 2026-02-19 | UX checklist documented in spec | Added the concrete 10-point human-eyes QA checklist for UX Overhaul Task 9 to `docs/UX_OVERHAUL_SPEC.md`, including completed items and remaining manual sweeps (mobile + dark-mode pass). |
| 2026-02-19 | Mailer cooldown + explicit follow-up note | PLAN Item 14 now calls for suppressing duplicate sends of the same invoice notice within a configurable threshold unless the sender explicitly selects a distinct follow-up class (for example, “Second notice”); mirrored in FuturePLAN email notes. |
| 2026-02-18 | Future: client balance tracking + credits | Added a post-MVP FuturePLAN item for per-client credit balances and net outstanding totals (open balances less unspent credits), with an auditable credit ledger. |
| 2026-01-10 | UX Overhaul spec alignment | Clarified UX Overhaul as PLAN Item 13 and allowed client detail to route to edit until a dedicated show view is needed. |
| 2025-12-26 | Logout handles expired sessions | Token mismatches on logout now clear auth state and redirect instead of showing a 419; logout links now submit via buttons so GET `/logout` is never triggered. |
| 2025-12-20 | Additional wallets UI deferred | Hid the Additional wallets section, improved wallet input contrast in dark mode, and logged the deferral in `docs/FuturePLAN.md`. |
| 2025-12-20 | Login redirect to wallet setup | Logging in without a configured wallet now redirects to `/wallet/settings` so wallet setup happens before invoicing. |
| 2025-12-20 | Wallet UX improvements shipped | Wallet settings now include inline key guidance and derive validation preview with friendly errors; validation endpoint and tests updated. |
| 2025-12-17 | Wallet network: testnet3/testnet4 aliases | `WALLET_NETWORK` now accepts `testnet3`/`testnet4` so dev envs can stay explicit while derivation + watcher map to the correct networks. |
| 2025-12-17 | Helpful Notes: wallet key guide | Split xpub safety vs. “import your wallet key” steps, added BlueWallet-first instructions + sweeping guidance, and aligned Wallet Settings labels/errors with the supported formats. |
| 2025-12-16 | Future: Help CMS deferred | Logged a CMS-style, non-dev editable Help Center as a post-MVP item in `docs/FuturePLAN.md`; RC ships `/help` as a static SEO surface for now. |
| 2025-12-16 | Helpful Notes shipped | Added public `/help` with xpub/zpub safety guidance and seeded categories; deep-linked from Wallet Settings, linked from landing, and set up as an indexable SEO surface (canonical + description). |
| 2025-12-15 | UX Overhaul: Helpful Notes scoped | Added UX Overhaul ToDo #6 for a public “Helpful Notes” section (starting with xpub safety + why we ask), and renumbered wallet UX ToDo references. |
| 2025-12-11 | Theme toggle accessibility polish shipped | Added accessible labels/pressed-state semantics, visible focus rings, and larger tap targets to the light/dark/system header toggle; aligned with UX guardrails. |
| 2025-12-11 | UX Overhaul ToDo priority updated | Moved theme-toggle accessibility polish ahead of wallet UX and renumbered UX Overhaul ToDos; updated wallet spec references accordingly. |
| 2025-12-11 | UX guardrails reference added | Added `docs/UX_GUARDRAILS.md` (Nielsen/WCAG + form/accessibility guardrails) and linked it from AGENTS, PLAN Milestone 13, and README. |
| 2025-12-07 | Wallet network helper + validation | Wallet settings now hide network UI on mainnet, show a testnet-only helper, and enforce network-specific xpub prefixes for primary and additional wallets. |
| 2025-12-06 | Wallet/Xpub UX breakout spec | Added `docs/WALLET_XPUB_UX_SPEC.md` for wallet UX scope; linked UX Overhaul spec + PLAN to the detailed wallet flow/copy/validation guidance. |
| 2025-12-06 | Wallet UI testnet badge scope | PLAN and UX spec now clarify mainnet-default UI: env-driven network with no mainnet badge and a dev-only testnet badge/helper shown only when `WALLET_NETWORK` is testnet. |
| 2025-12-06 | MS12 moved to completed milestones | PLAN now lists Payment & Address Accuracy as completed, with verified derivation checks and watcher sanity runs on invoices 7/8/10; outstanding sats clamp documented. |
| 2025-12-06 | MS12 payment/address verification | Verified corrected derivations on invoices 7/8/10 (testnet indices 11/12/13) and ran `wallet:watch-payments --invoice=7,8,10`; watcher processed paid/partial states as expected with no derivation mismatches. |
| 2025-12-05 | Mailer & Alerts scope renamed | PLAN Item 14 is now “Mailer & Alerts Polish + Audit” and explicitly includes copy review/refresh for all outbound messages alongside the pipeline/queue audit. |
| 2025-12-04 | Small-balance resolution spec | Documented exact outstanding display (no dust masking) and a manual “Resolve small balance” adjustment (threshold = `max($1, min(1% of expected USD, $50))`) to settle tiny residual USD and mark invoices paid; see PLAN and PARTIAL_PAYMENTS. |
| 2025-12-03 | Partial payments spec clarified | Documented USD-as-source with per-payment rate locking, floating outstanding BTC for QR, and status gating on confirmed USD (pending/partial/paid); aligned PAYMENT_CONFIRMATIONS and PLAN notes. |
| 2025-12-01 | Payment & address accuracy scope expanded | PLAN Item 12 now covers confirmation-gated payments (`pending` until confirmed), RBF-safe cleanup for dropped unconfirmed txids, and configurable confirmation thresholds (default 1; per-user post-RC). |
| 2025-11-23 | PLAN mainnet cutover item added | Inserted mainnet cutover prep ahead of RC deploy and clarified Item 12 as testnet-focused; RC checklist remains linked. |
| 2025-11-23 | RC rollout checklist added | Added `docs/RC_ROLLOUT_CHECKLIST.md`, linked PLAN item 16 verification and README docs section. |
| 2025-11-23 | Partial-payment warning documented | NOTIFICATIONS now lists the proactive partial-payment client warning + owner FYI already described in PARTIAL_PAYMENTS; PLAN/README linked new DX docs. |
| 2025-11-23 | Wallet settings mainnet-first | Network selector removed; WALLET_NETWORK env sets network, UI shows badge, xpub validation updated; additional wallets follow the configured network. |
| 2025-11-23 | Light/Dark theme toggle | Per-user theme preference (light/dark/system) added with header toggle, class-based dark mode, and tests; PLAN/UX spec updated to mark it done. |
| 2025-11-23 | UX Overhaul spec updated | Marked dashboard snapshot complete; broadened UI polish to invoices/clients and settings/auth; outputs/DoD realigned with current ToDo list. |
| 2025-11-23 | PLAN alignment updates | PLAN UX Overhaul section synced with spec and mailer/alerts audit now links to `docs/NOTIFICATIONS.md`. |
| 2025-11-23 | Landing page refreshed | Dark-themed CryptoZing hero with larger logos, updated copy, staggered feature cards, and CTA/brand badge polish. |
| 2025-11-22 | Dashboard snapshot added | Dashboard now shows open/past-due counts, outstanding totals, recent payments with per-user cached snapshot. |
| 2025-11-22 | UX Overhaul spec added | Added `docs/UX_OVERHAUL_SPEC.md` and linked PLAN/README for Item 12 scope. |
| 2025-11-22 | Print view + timezone fixes | Print view hides back link on public, uses invoice-created date (Denver), formats BTC/rate via shared helpers; changelog column switched to America/Denver; app timezone set to America/Denver. |
| 2025-11-22 | UX Overhaul ordering set | PLAN Item 12 reordered (dashboard → wallet UX → show/edit polish → public/share refresh → onboarding wizard → toggles → templates → settings polish). |
| 2025-11-22 | Docs & DX spec drafted | Added `docs/DOCS_DX_SPEC.md`, reordered PLAN to prioritize UX Overhaul before Docs/DX, and linked README. |
| 2025-11-07 | PR #13 merged | Friendly 403 copy standardized to satisfy Authorization tests. |
| 2025-11-07 | Docker daemon access adjusted | AL9 configured for Sail via socket group. |
| 2025-11-07 | PLAN.md established | Plan maintained by Codex; README links to doc. |
| 2025-11-07 | Added test hardening suites | Public share SEO, rate refresh caching, BIP21 output, and trash/restore flows covered by Sail tests. |
| 2025-11-07 | Documented rate precision | `docs/RATES.md` defines USD/BTC rounding and cache TTL rules. |
| 2025-11-08 | Wallet onboarding & derived addresses | `/wallet/settings`, Node-based derivation, and legacy backfill command landed (codex/phase-a-wallet). |
| 2025-11-10 | Blockchain watcher command wired | `wallet:watch-payments` + mempool client integrated into bootstrap; invoices now auto-mark when payments land. |
| 2025-11-10 | Watcher scheduling automated | Scheduler runs `wallet:watch-payments` every minute with overlap protection + background execution. |
| 2025-11-14 | Partial payments UI + outstanding summaries | `invoice_payments` table shipped with watcher backfill, USD-first summaries, and outstanding-targeted QR/BIP21 output. |
| 2025-11-15 | Invoice delivery + automated receipts | `invoice_deliveries` log, manual send form, queue job, and `auto_receipt_emails` toggle landed (codex/invoice-delivery). |
