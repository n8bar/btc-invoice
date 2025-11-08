# Future Plan (Post-MVP)
_Working list of initiatives queued after the MVP ships. Maintained alongside docs/PLAN.md._

## Infrastructure & Payments
1. **Self-Hosted Bitcoin Node + Watcher**
   - Deploy bitcoind + Electrum indexer or mempool.space instance.
   - Replace third-party payment detection with our own RPC/WS hooks.
   - Automate failover/monitoring for mempool + confirmation events.

2. **Multisig / Advanced Wallet Support**
   - Support xpub/zpub from multi-sig wallets (BIP48 or custom policies).
   - Allow per-invoice address derivation from multiple co-signers.

## Email & Notifications
3. **Receipt PDFs for Paid Invoices**
   - Once payments auto-mark invoices as paid, generate immutable PDF receipts (rate + tx snapshot).
   - Email customers/owners with attachments + status history.

4. **Notification Hub**
   - Slack/webhook integrations for payment events, delivery failures, etc.

## UX & Dashboard
5. **Onboarding Wizard**
   - Friendlier xpub collection (wallet-specific steps, QR parsing, validation).
   - Guided wallet/testnet setup before first invoice.

6. **Dashboard Redesign**
   - At-a-glance stats (outstanding vs paid, latest payments, delivery issues).
   - Quick actions for resending invoices or copying share links.

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
