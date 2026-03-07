# UX Overhaul Spec (RC)

Scope and Definition of Done for PLAN Item 13. Focus: tighten core UX flows before RC.

## Outputs
- Dashboard snapshot redesign that surfaces invoice/client health at a glance.
- Wallet UX improvements (xpub guidance, network cues, validation helpers).
- Public “Helpful Notes” section (short, plain-language explanations; starts with xpub safety + why we ask).
- Invoices & Clients UI polish (CRUD surfaces, show/edit flows, print/public/share, delivery logs/actions).
- Public/share layouts refresh to mirror updated show/print patterns.
- Guided onboarding wizard (wallet setup → create invoice → deliver).
- User-level communication toggles for overpayment note and QR refresh reminder.
- Settings/auth consistency polish (Account, Invoice Settings, Wallet Settings, Login/Logout) using existing patterns.

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
4. Login screen refresh
   - Replace Laravel branding on auth/login with CryptoZing styling and logo; keep form copy concise and consistent with the new dark/light theme.
   - Keep the page lean vs. the landing hero: single brand mark + short tagline, minimal background treatment, and no feature pills/marketing blocks.
   - Layout: narrow form column with generous whitespace, clear error/success states, and a secondary link to register (if enabled) without competing CTAs.
   - Theming: respect system `prefers-color-scheme` with dark fallback; use the same CSS-variable palette for light/dark applied via `data-theme`, and define an `auth-card` component (spacing, radius, border, shadow) centrally rather than inline.
   - Guest header tagline on auth pages: pick from a small approved list and keep it stable per session.
5. Theme toggle accessibility polish (guardrail follow‑up on Completed Task #2)
   - Added accessible labels and `aria-pressed` state to light/dark/system buttons.
   - Increased tap targets and added visible focus rings; active state is clear in both themes.
6. Helpful Notes (public)
   - Added `/help` (no auth) using the core layout/theme; intended to be indexable (SEO surface) with a canonical URL and meta description.
   - Seeded Wallet & Security note explaining extended public keys (xpub/zpub): what they are, why we ask, what they can’t do (can’t spend), and privacy implications; explicitly states we never ask for seed phrases or private keys.
   - Added an “Importing your wallet account key (xpub / zpub)” note with BlueWallet-first steps, general guidance, post-payment sweeping guidance, and a seed-import warning as a last resort.
   - Structured page for growth with Wallet & Security, Invoices, Payments, and Privacy categories.
   - Context link added on Wallet Settings (xpub guidance) that deep-links into `/help#import-wallet-key` and shows a “Back to Wallet Settings” affordance when linked from that screen.
   - SEO note: keep titles/descriptions/copy/heading structure polished over time; `/help?from=…` should canonicalize to `/help` to avoid duplicate indexing.
   - Post-MVP: a CMS-style, non-dev editable Help Center is tracked in `docs/FuturePLAN.md`.
7. Wallet UX improvements
   - Wallet settings now include inline guidance and derive validation preview; additional wallets UI is deferred to post-RC.
8. Login redirect to wallet setup (interim bridge)
   - Temporary pre-wizard bridge shipped before Task 11: logging in without a wallet redirected to `/wallet/settings`.
   - Superseded by the Task 11 getting-started flow, which now owns login entry for incomplete users.
9. Invoices & Clients UI polish (Completed)
    - Cover core CRUD surfaces: clients index/detail/create/edit, invoices index/show/create/edit/print/public/share, and delivery/receipt flows.
    - Client detail can temporarily route to edit (no separate show screen) as long as navigation stays obvious.
    - Ensure show views expose edit/delete/restore actions with consistent placement and confirmations; empty states are helpful and guide to next steps.
    - Verify forms (clients + invoices) have clear validation, inline errors, and layout consistency; titles/labels/buttons align with nav ordering and CTA patterns.
    - Revisit invoice show/edit behavior (Edit button on show, return to show after save), billing/payment summary layout, and alerts for public/print/share states.
    - Check related utilities: trash/restore/force-delete flows, share enable/disable/rotate, and delivery send/receipt toggles retain UX polish and authorization cues.
    - Human-eyes QA checklist (2026-02-19 working set):
      - [x] Status pills on invoice show are readable in light/dark (`SENT` light-blue with dark text; `PARTIAL` cyan with dark text; `VOID` remains readable in dark mode).
      - [x] Colored cards use matching border/text color treatment (`border-color: currentColor`) on invoice/client/settings surfaces.
      - [x] Draft warning logic only appears for draft invoices with real on-chain payments (not manual-adjustment-only cases).
      - [x] Draft warning CTA uses text-link `Mark sent` and successfully updates status.
      - [x] Invoice show section order is: Payment QR -> Delivery log -> Payment history -> Public link.
      - [x] Public link `Open`/copy actions use the configured host for dev (`http://192.168.68.25/...` when `APP_PUBLIC_URL` is set accordingly).
      - [x] Edit-page public notice copy/link pattern: `Or open invoice details.` with only `open invoice details` linked.
      - [x] Delete cards have visible red borders in light mode on both invoice edit and client edit screens.
      - [x] Mobile sanity sweep: no horizontal overflow and action bars/buttons wrap cleanly across invoice/client pages.
      - [x] Dark-mode readability sweep: spot-check all major invoice/client states and notices after recent style changes.
    - Follow-up closure (2026-02-21): authenticated invoice/client narrower-screen action-row sweep complete; temporary Task 9 ToDo split merged back into this completed task.
10. Public/share refresh (Completed)
    - Public and print views now share a single template entrypoint and common partial blocks to keep wording/layout in sync.
    - Explicit public states (`active` vs `disabled_or_expired`) render safe unavailable messaging with owner contact details while hiding payment details and owner-only controls when links are not active.
    - Public view preserves Print + noindex/noarchive behavior and mirrors print/show section language for active links.
    - Task 10 acceptance checklist is fully complete, including narrower-screen public/share sanity verification (2026-02-23).
    - Coverage includes `PublicShareTest` (active/disabled states, noindex, owner-control exclusions) and `InvoicePaymentDisplayTest` parity checks.
11. Onboarding wizard (Completed)
   - `GET /getting-started` and `GET /getting-started/{step}` (`wallet`, `invoice`, `deliver`) now provide a lightweight step shell with dismiss/reopen support and no skip-ahead routing.
   - Progress is derived from real app data (wallet setting, invoices, public-enabled invoice + delivery log) with minimal persisted user state (`getting_started_completed_at`, `getting_started_dismissed`).
   - Existing pages remain the source of truth for the actual work; wallet settings, invoice create, and invoice show render a compact getting-started progress strip when reached from the flow.
   - Context-aware success redirects resume the flow after wallet save, invoice create, and invoice delivery; v1 auto-show behavior is login redirect + dashboard/invoice empty-state/menu prompts (no global route interception).
   - Coverage includes `GettingStartedFlowTest` and integration assertions in auth/wallet/invoice delivery/show test suites.
12. User settings & auth UX (Completed, 2026-03-05)
   - Initial implementation shipped communication toggles on Profile and persisted them per user (default on): `show_overpayment_gratuity_note` and `show_qr_refresh_reminder`.
   - Invoice show/public/print copy gating shipped for overpayment gratuity messaging and QR refresh/staleness reminders.
   - Guardrail upheld: owner reconciliation/operational guidance remains visible when client-facing note toggles are off.
   - Browser QA checklist completed (items 1-15).
   - Scope remained implementation-light for settings/auth consistency (Account, Invoice Settings, Login/Logout) without introducing auth-flow redesign.

## ToDo
13. Invoice settings and invoice UX finish-up
   - Phase A — Pre-implementation Browser QA (baseline, lightweight)
     1. [x] Invoice create (`/invoices/create`): capture current Branding & footer behavior, including how defaults/overrides are currently shown.
     2. [x] Invoice Settings (`/settings/invoice`): capture current placeholder/default behavior for branding heading and related helper text.
     3. [x] Over/underpayment alerts (gratuity note ON/OFF): capture current public/print copy wording and biller-name references.
     4. [x] Confirmed Task13 quality priorities during baseline review: heading/footer/address microcopy clarity, focus/error parity, and save-state consistency.
   - Phase B — Implementation
     1. [x] Invoice create flow simplification: remove the status dropdown from create and always create new invoices as `draft` by default. Users can change status after creation on invoice show/edit actions.
     2. [x] Preserve existing per-invoice override behavior; no structural redesign.
     3. [x] Require client email on client create/edit and enforce it at the database layer (`clients.email` non-null) with a safe migration/backfill path for any existing null rows.
     4. [x] Paid-invoice print polish: render a prominent, translucent diagonal `PAID` watermark on paid print views (owner print and active public print) so payment state is unmistakable in exported/printed copies.
     5. [x] Client-facing over/underpayment wording polish: replace generic “invoice sender” phrasing with biller/brand-facing wording (use invoice billing name with a safe fallback) so public/print copy reads as authored by the invoice owner.
     6. [x] Paid-invoice payment-action safety: hide payment QR + BIP21/copy payment action surfaces once an invoice is paid across owner and client views (including owner invoice show, owner print, and active public print) to reduce accidental extra payments from rescanning old invoices.
     7. [x] Branding & footer reset affordance: add a clear “Reset to my custom defaults” action near the top of the create/edit Branding & footer section so users can quickly revert per-invoice overrides back to Invoice Settings defaults.
     8. [x] Hide the editable invoice-level `TXID` field from owner invoice edit UI (keep backend/internal compatibility for legacy/manual/recovery workflows).
     9. [x] Move the owner invoice “Footer note” card so it renders immediately before “Payment Details” instead of near the top action/status area.
     10. [x] IA correction implementation: move the overpayment gratuity note toggle and QR refresh reminder toggle from Profile UI into Invoice Settings UI, keeping persistence/default behavior user-level for now.
     11. [x] Settings IA shell: add a unified Settings surface with tabs for `Account`, `Wallet`, `Invoices`, and `Notifications` (initial pass can reuse existing forms/routes behind the new tab shell), and keep the settings tab bar sticky so it remains visible while scrolling settings pages.
     12. [x] Keep `Show invoice IDs in list` as an Account preference (under `Settings > Account`), not under invoice-specific settings.
     13. [x] Move `Auto email paid receipts` from Account into `Settings > Notifications` as outbound communication behavior.
     14. [x] Account-menu cleanup: collapse `Account`, `Wallet`, and `Invoice Settings` into one `Settings` entry (default tab: `Account`) so account menu items are only `Settings`, `Getting Started`, and `Logout`.
   - Phase C — Post-implementation Browser QA (acceptance + regression)
     1. [ ] Re-run Phase A checks and confirm intended behavior changes shipped without regressions.
     2. [ ] Verify invoice create now always starts as `draft` and no create-time status selector is shown.
     3. [ ] Verify client email is required in create/edit with clear validation copy and schema-backed enforcement.
     4. [ ] Verify paid invoice surfaces (owner show + owner print + public print) match scope: payment-action surfaces hidden where specified and `PAID` watermark behavior matches implementation.
     5. [ ] Verify over/underpayment client-facing copy (gratuity ON/OFF paths) is actionable and biller-branded.
     6. [ ] Verify Branding & footer reset-to-defaults control works on create/edit without breaking existing per-invoice override behavior.
     7. [ ] Verify the owner invoice Footer note now renders immediately above Payment Details and no longer appears in the top status/action area.
     8. [ ] Verify both communication toggles now live in Invoice Settings and are no longer shown on Profile.
     9. [ ] Verify Settings shell exposes `Account`, `Wallet`, `Invoices`, and `Notifications` tabs with stable navigation/active-state cues and sticky persistence while scrolling.
     10. [ ] Verify `Show invoice IDs in list` appears only in `Settings > Account` and still controls invoice-list column visibility.
     11. [ ] Verify `Auto email paid receipts` appears in `Settings > Notifications`, persists correctly, and is absent from `Settings > Account`.
     12. [ ] Verify account menu contains only `Settings`, `Getting Started`, and `Logout`; `Settings` opens the unified Settings surface on the `Account` tab.

## Definition of Done
- All MS13 outputs above implemented or explicitly deferred with clear pointers.
- UX changes reflected across invoices/clients CRUD, show/public/print/share/delivery flows without breaking auth or ownership constraints.
- Task12 toggles are persisted per user (default on) and control only the intended client-facing copy in show/public/print.
- Settings/auth screens in Task12 scope (Account, Invoice Settings, Login/Logout) match guardrails and updated UX patterns without introducing an auth-flow redesign.
- Tests updated/added for new flows and toggles; public views remain noindex and 403-safe.
- Docs (PLAN + onboarding/quick start later) updated after UX ships; changelog entries added per milestone.
