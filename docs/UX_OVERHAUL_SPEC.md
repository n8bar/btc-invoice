# UX Overhaul Spec (RC)

Scope and Definition of Done for PLAN Item 12. Focus: tighten core UX flows before RC.

## Outputs
- Dashboard snapshot redesign that surfaces invoice/client health at a glance.
- Wallet UX improvements (xpub guidance, network cues, validation helpers).
- Invoices & Clients UI polish (CRUD surfaces, show/edit flows, print/public/share, delivery logs/actions).
- Public/share layouts refresh to mirror updated show/print patterns.
- Guided onboarding wizard (wallet setup → create invoice → deliver).
- User-level toggles for overpayment note and QR refresh reminder.
- Per-user editable email templates (invoice send/reminders/alerts).
- Settings/auth polish (Profile, Invoice Settings, Wallet Settings) and branded Login/Logout UX.

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
2. Light/Dark mode toggle
   - Theme preference stored per user (`light`/`dark`/`system`) with class-based dark mode and `prefers-color-scheme` fallback.
   - Header toggle (desktop + mobile) updates UI immediately and persists via auth route; layout applies theme to root, and Tailwind dark mode is class-based.
   - Tests cover preference persistence and validation.
3. Landing page refresh
   - Dark-themed CryptoZing hero with updated headline/copy, larger logos (brand badge + hero image), CTA/button states, and staggered feature cards (wallet-ready, client-friendly, accurate, on-chain aware, delivery-ready, public links).

## ToDo
4. Wallet UX improvements
   - Inline explainer for xpub formats per network; show network badge; derive-test feedback inline.
   - Clear error states on invalid xpub/derivation failure; minimal scrolling for key fields.
5. Invoices & Clients UI polish
   - Cover core CRUD surfaces: clients index/detail/create/edit, invoices index/show/create/edit/print/public/share, and delivery/receipt flows.
   - Ensure show views expose edit/delete/restore actions with consistent placement and confirmations; empty states are helpful and guide to next steps.
   - Verify forms (clients + invoices) have clear validation, inline errors, and layout consistency; titles/labels/buttons align with nav ordering and CTA patterns.
   - Revisit invoice show/edit behavior (Edit button on show, return to show after save), billing/payment summary layout, and alerts for public/print/share states.
   - Check related utilities: trash/restore/force-delete flows, share enable/disable/rotate, and delivery send/receipt toggles retain UX polish and authorization cues.
6. Public/share refresh
   - Public and print views share visual language (headings, notes, footer).
   - Disabled/expired states stay friendly with contact info; no owner-only controls exposed.
7. Onboarding wizard
   - Guides: connect wallet → create invoice → enable share/deliver.
   - Can be dismissed/completed; links into existing forms; no bypass of auth/policies.
   - Provides the empty-state prompts (no wallet or no invoices) that link into the wizard where appropriate; surface “connect wallet/create first invoice” calls to action.
8. User settings & auth UX
   - Overpayment note and QR refresh reminder toggles live under profile/settings; persist per user and drive conditional copy in show/public/print.
   - Polish Profile, Invoice Settings, and Wallet Settings pages: clear grouping, validation/error states, helper text, and consistent action buttons.
   - Login/Logout UX: ensure branded, accessible, and consistent with the updated theme; error/success states are friendly and clear.
9. Editable email templates
   - Per-user editable subject/body for client-facing emails (invoice send, reminders/alerts) with safe variables.
   - Preview + reset-to-default; validation to prevent empty required tokens.
10. Invoice Settings polish
   - Branding defaults UI cleanup; copy hints for footer/heading/address; preserves overrides.

## Definition of Done
- All outputs above implemented or explicitly deferred to FuturePLAN with pointers.
- UX changes reflected across invoices/clients CRUD, show/public/print/share/delivery flows without breaking auth or ownership constraints.
- Settings/auth screens (Profile, Invoice Settings, Wallet Settings, Login/Logout) match the updated UX patterns; per-user toggles and editable templates behave as specified.
- Tests updated/added for new flows and toggles; public views remain noindex and 403-safe.
- Docs (PLAN + onboarding/quick start later) updated after UX ships; changelog entries added per milestone.
