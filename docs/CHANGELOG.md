# Project Changelog

| Date (UTC) | Change | Notes |
|------------|--------|-------|
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
