# Project Changelog

| Date (America/Denver) | Change | Notes |
|-----------------------|--------|-------|
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
