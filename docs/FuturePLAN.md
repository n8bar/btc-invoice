# Future Plan (Post-MVP)
_Working list of initiatives queued after the MVP ships. Maintained alongside docs/PLAN.md._

Latest scope update (2025-11-16): partial payments, payment history UX, and invoice delivery + auto receipts are live; backlog below covers post-MVP bets.

## Infrastructure & Payments
1. **Self-Hosted Bitcoin Node + Watcher**
   - Deploy bitcoind + Electrum indexer or mempool.space instance.
   - Replace third-party payment detection with our own RPC/WS hooks.
   - Automate failover/monitoring for mempool + confirmation events.

2. **Multisig / Advanced Wallet Support**
   - Support xpub/zpub from multi-sig wallets (BIP48 or custom policies).
   - Allow per-invoice address derivation from multiple co-signers.
   - Note: small-balance resolution (manual credit to close dust residuals) is now defined in the active PLAN/PARTIAL_PAYMENTS spec.

## Email & Notifications
3. **Receipt PDFs for Paid Invoices**
   - Once payments auto-mark invoices as paid, generate immutable PDF receipts (rate + tx snapshot).
   - Email customers/owners with attachments + status history.
   - Extend the current `InvoiceReadyMail`/`InvoicePaidReceipt` flows so `invoice_deliveries` rows capture PDF metadata + sent status in one place.

4. **Notification Hub**
   - Slack/webhook integrations for payment events, delivery failures, etc.
   - Reuse `InvoicePaid` events and delivery log updates to emit notifications without polling.

# Observability & Ops
5. **Structured Logging & Alerting**
   - Centralized log ingestion (ELK/Loki) for rate fetches, payment events, mail sends.
   - Alerts when blockchain watcher or mail queue falls behind.

6. **Automated Deployments**
   - Terraform/Ansible for infrastructure, CI/CD pipeline for staging/prod.

# Integrations
7. **Accounting Export**
   - CSV/JSON exports for accounting packages (QuickBooks, Xero).
   - API hooks so agencies can pull invoice/payment data programmatically.

8. **Fiat On-Ramp / Volatility Tools**
    - Optional integration with OTC partners or conversion APIs for users who want instant conversion.

9. **Additional Cryptocurrencies**
    - Track alternate chain addresses per invoice and support derivation/sending for other cryptocurrencies once Bitcoin MVP stabilizes.

10. **Manual Payment Adjustments**
    - Admin tooling to edit or annotate logged payments (fix tx metadata, override amounts, or reconcile disputes) outside the automated watcher flows.
    - Build atop the `invoice_payments` ledger + owner notes so adjustments stay auditable and raw tx rows remain untouched.

# Content & SEO
11. **CMS-style Help Center (public)**
   - Move `/help` content out of Blade into editable entries (prefer Markdown) with safe rendering and stable section slugs/anchors.
   - Add an internal editor UI with preview, draft/publish, and basic revision history so non-devs can update copy without deployments.
   - Store per-wallet “find your extended public key” guides as data so tabs are config-driven (easy to add/remove wallets).
   - Keep SEO hygiene: canonical URLs, meta descriptions/OG, and avoid indexing duplicate `?from=` variants.
