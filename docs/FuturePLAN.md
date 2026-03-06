# Future Plan (Post-MVP)
_Working list of initiatives queued after the MVP ships. Maintained alongside docs/PLAN.md._

Latest scope update (2026-03-05): no new post-MVP backlog items in this pass; active PLAN/UX spec refined MS13 Task13 scope wording (invoice settings + invoice UX finish-up), kept the invoice-create simplification (new invoices always start as draft, status set after creation), and added client-email hardening (required on create/edit + `clients.email` non-null schema target), while this doc remains focused on deferred post-MVP items such as invoices/clients search+filter and multi-wallet selection UX.

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
   - Note: small-balance resolution (manual credit to close dust residuals) is now defined in the active PLAN/PARTIAL_PAYMENTS spec.

## Email & Notifications
_Carry-forward guardrail from active PLAN Item 15: suppress duplicate sends for the same invoice + notice class inside a configurable cooldown window unless an explicit follow-up class (for example, “Second notice”) is selected._

4. **Receipt PDFs for Paid Invoices**
   - Once payments auto-mark invoices as paid, generate immutable PDF receipts (rate + tx snapshot).
   - Email customers/owners with attachments + status history.
   - Extend the current `InvoiceReadyMail`/`InvoicePaidReceipt` flows so `invoice_deliveries` rows capture PDF metadata + sent status in one place.

5. **Notification Hub**
   - Slack/webhook integrations for payment events, delivery failures, etc.
   - Reuse `InvoicePaid` events and delivery log updates to emit notifications without polling.

# Observability & Ops
6. **Structured Logging & Alerting**
   - Centralized log ingestion (ELK/Loki) for rate fetches, payment events, mail sends.
   - Alerts when blockchain watcher or mail queue falls behind.

7. **Automated Deployments**
   - Terraform/Ansible for infrastructure, CI/CD pipeline for staging/prod.

# Integrations
8. **Accounting Export**
   - CSV/JSON exports for accounting packages (QuickBooks, Xero).
   - API hooks so agencies can pull invoice/payment data programmatically.

9. **Fiat On-Ramp / Volatility Tools**
    - Optional integration with OTC partners or conversion APIs for users who want instant conversion.

10. **Additional Cryptocurrencies**
    - Track alternate chain addresses per invoice and support derivation/sending for other cryptocurrencies once Bitcoin MVP stabilizes.

11. **Manual Payment Adjustments**
    - Admin tooling to edit or annotate logged payments (fix tx metadata, override amounts, or reconcile disputes) outside the automated watcher flows.
    - Build atop the `invoice_payments` ledger + owner notes so adjustments stay auditable and raw tx rows remain untouched.

# Content & SEO
12. **CMS-style Help Center (public)**
   - Move `/help` content out of Blade into editable entries (prefer Markdown) with safe rendering and stable section slugs/anchors.
   - Add an internal editor UI with preview, draft/publish, and basic revision history so non-devs can update copy without deployments.
   - Store per-wallet “find your extended public key” guides as data so tabs are config-driven (easy to add/remove wallets).
   - Keep SEO hygiene: canonical URLs, meta descriptions/OG, and avoid indexing duplicate `?from=` variants.

# Product UX
13. **Multi-wallet selection + additional wallets UI**
   - Core wallet-key lineage/cursor safety is now tracked in active PLAN MS14.1; this post-MVP item builds on that foundation.
   - Re-enable the Additional wallets UI in `/wallet/settings` once multi-wallet selection is in scope.
   - Add an invoice-level wallet selector and migration guidance for existing invoices.

14. **Client balance tracking + credits**
   - Track per-client credit balances that can be issued manually (refunds, overpayments, goodwill adjustments) and applied to future invoices.
   - Show each client’s net outstanding balance as: total open invoice balances minus unspent credit balance.
   - Surface an owner-facing ledger so credit issuance, application, reversal, and remaining credit are auditable over time.
