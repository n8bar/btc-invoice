# Backlog (Post-MVP)
_Last updated: 2026-03-18_

This is the canonical post-MVP backlog.

Use this file only for deferred or future work.
Anything still in RC scope belongs in [`docs/PLAN.md`](PLAN.md) and its linked canonical docs instead.

## Near-Term Product UX
1. **Invoices + Clients Search and Filtering**
   - Add list search on both `/invoices` and `/clients` (number/name/email/status query support as appropriate).
   - Add lightweight filters (status + date ranges on invoices; active/archived signal on clients if still relevant after soft-delete views).
   - Keep query params shareable/bookmarkable, and persist filter state across pagination.
   - Add feature coverage for query/filter combinations and ownership boundaries.

## Infrastructure & Payments
2. **Self-Hosted Bitcoin Node + Watcher**
   - Deploy bitcoind + Electrum indexer or mempool.space instance.
   - Replace third-party payment detection with our own RPC/WS hooks.
   - Automate failover/monitoring for mempool + confirmation events.

3. **Multisig / Advanced Wallet Support**
   - Support xpub/zpub from multi-sig wallets (BIP48 or custom policies).
   - Allow per-invoice address derivation from multiple co-signers.
   - Note: small-balance resolution (manual credit to close dust residuals) is now defined in the active product spec and partial-payments spec.

## Email & Notifications
_Carry-forward guardrail from active roadmap scope: suppress duplicate sends for the same invoice and notice class inside a configurable cooldown window unless an explicit follow-up class is selected._

4. **Receipt PDFs for Paid Invoices**
   - Once payments auto-mark invoices as paid, generate immutable PDF receipts (rate + tx snapshot).
   - Email customers/owners with attachments + status history.
   - Extend the current `InvoiceReadyMail` and `InvoicePaidReceipt` flows so `invoice_deliveries` rows capture PDF metadata + sent status in one place.

5. **Notification Hub**
   - Slack/webhook integrations for payment events, delivery failures, etc.
   - Reuse `InvoicePaid` events and delivery log updates to emit notifications without polling.

6. **Notification Preference Expansion**
   - Consider allowing owners to configure alert thresholds per profile instead of keeping the RC-wide default threshold.

## Observability & Ops
7. **Structured Logging & Alerting**
   - Centralized log ingestion (ELK/Loki) for rate fetches, payment events, mail sends.
   - Alerts when blockchain watcher or mail queue falls behind.

8. **Automated Deployments**
   - Terraform/Ansible for infrastructure, CI/CD pipeline for staging/prod.

## Integrations
9. **Accounting Export**
   - CSV/JSON exports for accounting packages (QuickBooks, Xero).
   - API hooks so agencies can pull invoice/payment data programmatically.

10. **Fiat On-Ramp / Volatility Tools**
   - Optional integration with OTC partners or conversion APIs for users who want instant conversion.

11. **Additional Cryptocurrencies**
   - Track alternate chain addresses per invoice and support derivation/sending for other cryptocurrencies once Bitcoin MVP stabilizes.

12. **Advanced payment-ledger admin tooling**
   - This is not the existing owner-facing manual adjustment flow that already closes small balances or records credit/debit adjustments on an invoice.
   - This is post-MVP operator tooling to edit or annotate logged payment records themselves: fix tx metadata, override imported amounts in exceptional cases, reconcile disputes, or correct ledger history without relying solely on automated watcher flows.
   - Build atop the `invoice_payments` ledger + owner notes so changes stay auditable and raw tx history is preserved rather than silently overwritten.

## Content & SEO
13. **CMS-style Help Center (public)**
   - Move `/help` content out of Blade into editable entries (prefer Markdown) with safe rendering and stable section slugs/anchors.
   - Add an internal editor UI with preview, draft/publish, and basic revision history so non-devs can update copy without deployments.
   - Store per-wallet “find your extended public key” guides as data so tabs are config-driven (easy to add/remove wallets).
   - Keep SEO hygiene: canonical URLs, meta descriptions/OG, and avoid indexing duplicate `?from=` variants.

## Product UX
14. **Multi-wallet selection + additional wallets UI**
   - Core wallet-key lineage and cursor safety is now tracked in active RC roadmap scope; this post-MVP item builds on that foundation.
   - Re-enable the Additional wallets UI in `/wallet/settings` once multi-wallet selection is in scope.
   - Add an invoice-level wallet selector and migration guidance for existing invoices.

15. **Quotes + quote-to-invoice conversion**
   - Treat quotes as proposal documents and invoices as billing documents; do not collapse both concepts into one shared lifecycle.
   - Keep quote status and invoice status as separate fields so proposal progress and billing/payment progress are modeled independently.
   - Initial direction: quote lifecycle `draft`, `sent`, `accepted`, `declined`, `expired`; invoice lifecycle keeps its own `unsent`, `sent`, `pending`, `partial`, `paid`, `void` states.
   - Converting an accepted quote into an invoice should create a linked billing record whose invoice lifecycle starts at `unsent`.
   - During future spec work, decide whether quotes and invoices share physical storage or use separate tables, but preserve clear quote/invoice identity and relationship history either way.

16. **Client balance tracking + credits**
   - Track per-client credit balances that can be issued manually (refunds, overpayments, goodwill adjustments) and applied to future invoices.
   - Show each client’s net outstanding balance as: total open invoice balances minus unspent credit balance.
   - Surface an owner-facing ledger so credit issuance, application, reversal, and remaining credit are auditable over time.

17. **Spending-only companion wallet ecosystem idea**
   - Separate product idea, not RC scope for CryptoZing itself.
   - Explore a companion wallet app that can spend/send but does not receive, and only shares public derivation material with CryptoZing.
   - Goal: reduce accidental outside receive activity on the same account namespace while keeping CryptoZing watch-only.
   - Future direction to evaluate later: whether a tighter CryptoZing + companion-wallet pairing should be encouraged or even required in a future product generation.
