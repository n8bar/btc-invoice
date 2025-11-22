# UX Overhaul Spec (RC)

Scope and Definition of Done for PLAN Item 12. Focus: tighten core UX flows before RC.

## Outputs
- Dashboard snapshot redesign that surfaces invoice/client health at a glance.
- Wallet UX improvements (xpub guidance, network cues, validation helpers).
- Invoice show/edit polish (add Edit on show → return to show after save; layout tweaks).
- Public/share layouts refresh to mirror updated show/print patterns.
- Guided onboarding wizard (wallet setup → create invoice → deliver).
- User-level toggles for overpayment note and QR refresh reminder.
- Per-user editable email templates (invoice send/reminders/alerts).
- Invoice Settings polish (branding defaults, layout/copy refinements).

## Completed Tasks
1. Dashboard snapshot redesign (counts/totals/recent payments).
   - Metrics: open invoices, past-due invoices, outstanding USD total, optional outstanding BTC (using stored rate), recent payments (5), and “unpaid/partial” breakdown.
   - Data layer: `DashboardSnapshotService` queries only the authenticated user’s data, eager-loads minimal fields, and caches aggregates per user (60s) to avoid heavy dashboard hits.
   - Queries/scopes: invoice scopes for `ownedBy`/`open`/`pastDue`/`dueSoon` and payment scopes for recent payments; respect soft deletes and ownership.
   - UI: card grid for counts/totals with clear labels and icons; “Recent payments” list/table with invoice number, client, amount paid, and a link to invoice show; CTA links to invoices index/clients/create on dashboard.
   - Routing: reuse `/dashboard`; inject snapshot data from controller; keep view logic minimal and avoid duplicating queries in Blade.
   - Edge cases: amounts format per `docs/RATES.md`; no public/share tokens exposed; detected_at fallback to created_at.
   - Tests: Feature coverage asserts counts/totals and recent payments list for owner, ignores foreign/trashed invoices, caps to 5 ordered payments, and checks per-user cache isolation/refresh expectations.
   - Performance: recent payments limited to 5 with minimal eager-loads; joins scoped by ownership and soft deletes.

## ToDo
2. Light/Dark mode toggle
   - Add a theme toggle in the header (left of user dropdown) that switches light/dark modes across the app; persist per user.
3. Landing page refresh
   - Replace Laravel branding on the welcome/landing page with CryptoZing branding/logo while keeping Login and Register buttons; ensure copy and visuals align with the app’s UX and brand.
4. Wallet UX improvements
   - Inline explainer for xpub formats per network; show network badge; derive-test feedback inline.
   - Clear error states on invalid xpub/derivation failure; minimal scrolling for key fields.
5. Invoice show/edit polish
   - Show view includes Edit button; edit redirects back to show on save.
   - Layout cleanup for billing info, payment summary, alerts; maintain policy enforcement.
6. Public/share refresh
   - Public and print views share visual language (headings, notes, footer).
   - Disabled/expired states stay friendly with contact info; no owner-only controls exposed.
7. Onboarding wizard
   - Guides: connect wallet → create invoice → enable share/deliver.
   - Can be dismissed/completed; links into existing forms; no bypass of auth/policies.
   - Provides the empty-state prompts (no wallet or no invoices) that link into the wizard where appropriate; surface “connect wallet/create first invoice” calls to action.
8. User toggles
   - Overpayment note and QR refresh reminder toggles live under profile/settings.
   - Toggles persist per user and drive conditional copy in show/public/print.
9. Editable email templates
   - Per-user editable subject/body for client-facing emails (invoice send, reminders/alerts) with safe variables.
   - Preview + reset-to-default; validation to prevent empty required tokens.
10. Invoice Settings polish
   - Branding defaults UI cleanup; copy hints for footer/heading/address; preserves overrides.

## Definition of Done
- All outputs above implemented or explicitly deferred to FuturePLAN with pointers.
- UX changes reflected in show/public/print without breaking auth or ownership constraints.
- Tests updated/added for new flows and toggles; public views remain noindex and 403-safe.
- Docs (PLAN + onboarding/quick start later) updated after UX ships; changelog entries added per milestone.
