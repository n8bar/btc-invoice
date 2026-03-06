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
- Settings/auth consistency polish (Profile, Invoice Settings, Wallet Settings, Login/Logout) using existing patterns.

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

## ToDo
12. User settings & auth UX (current-state rewrite, 2026-03-04)
   - Baseline already shipped before this task: branded login screen, grouped invoice settings cards, and existing profile toggles (`show_invoice_ids`, `auto_receipt_emails`).
   - Required implementation:
     - [x] Add two Profile toggles (reuse existing profile toggle pattern):
       - `show_overpayment_gratuity_note`
       - `show_qr_refresh_reminder`
     - [x] Persist per user (default `true` for existing and new users).
     - [x] Drive conditional copy on invoice show/public/print:
       - Overpayment gratuity note.
       - QR refresh/staleness reminder near payment QR surfaces.
   - Guardrail for this task: keep owner-operational warnings and reconciliation guidance visible even when client-facing note toggles are off.
   - Polish pass scope stays implementation-light:
     - [ ] Profile and Invoice Settings: grouping clarity, helper text, validation/error handling, visible focus, and consistent action buttons.
     - [ ] Login/Logout UX: branded + accessible consistency only (error/success wording and focus behavior), not a new auth flow.
   - Browser QA checklist (human-eyes):
     1. [x] Open `/profile` and confirm both toggles exist:
        - `Show overpayment gratuity note to clients`
        - `Show QR refresh reminder to clients`
     2. [x] Confirm both toggles default to ON for existing accounts after migration.
     3. [x] Save with both ON, hard refresh `/profile`, and confirm both remain ON.
     4. [x] Open owner invoice show (`/invoices/{id}`) and verify visible:
        - `Overpayments are treated as gratuities by default`
        - `Need to reconcile an over/under payment?`
        - `refresh right before sending payment; printed copies may be stale.`
     5. [x] Open print view (`/invoices/{id}/print`) and verify visible:
        - `Payment QR`
        - `Overpayments are treated as gratuities by default`
        - `refresh right before sending payment; printed copies may be stale.`
     6. [x] Open public view (`/p/{token}`) and verify the same two client-facing notes appear.
     7. [x] Set `Show overpayment gratuity note to clients` OFF and keep QR reminder ON; save.
     8. [x] Re-check owner show:
        - `Overpayments are treated as gratuities by default` is hidden.
        - `Need to reconcile an over/under payment?` remains visible.
     9. [x] Re-check print and public:
        - `Overpayments are treated as gratuities by default` is hidden.
        - QR refresh reminder remains visible.
     10. [x] Set gratuity ON and `Show QR refresh reminder to clients` OFF; save.
     11. [x] Re-check owner show, print, and public:
        - `refresh right before sending payment; printed copies may be stale.` is hidden.
        - `Payment QR` remains visible and functional.
     12. [x] Set both toggles OFF; save.
     13. [x] Re-check owner show, print, and public:
        - Both client-facing notes are hidden.
        - Owner reconciliation guidance remains visible on owner show.
     14. [x] Logout/login, return to `/profile`, and confirm persisted toggle state.
     15. [x] Keyboard/accessibility quick pass:
        - Tab focus ring is visible on each toggle.
        - Space toggles each checkbox.
        - Save still works from keyboard flow.
13. Invoice settings and invoice UX finish-up
   - 1) Use Task13 for invoice-settings and invoice UX deltas discovered during the Task12 consistency review.
   - 2) Priorities: heading/footer/address microcopy clarity, focus/error parity, and save-state consistency.
   - 3) Invoice create flow simplification: remove the status dropdown from create and always create new invoices as `draft` by default. Users can change status after creation on invoice show/edit actions.
   - 4) Preserve existing per-invoice override behavior; no structural redesign.
   - 5) Require client email on client create/edit and enforce it at the database layer (`clients.email` non-null) with a safe migration/backfill path for any existing null rows.
   - 6) Paid-invoice print polish: render a prominent, translucent diagonal `PAID` watermark on paid print views (owner print and active public print) so payment state is unmistakable in exported/printed copies.
   - 7) Client-facing over/underpayment wording polish: replace generic “invoice sender” phrasing with biller/brand-facing wording (use invoice billing name with a safe fallback) so public/print copy reads as authored by the invoice owner.
   - 8) Paid-invoice payment-action safety: hide payment QR + BIP21/copy payment action surfaces once an invoice is paid across owner and client views (including owner invoice show, owner print, and active public print) to reduce accidental extra payments from rescanning old invoices.

## Definition of Done
- All MS13 outputs above implemented or explicitly deferred with clear pointers.
- UX changes reflected across invoices/clients CRUD, show/public/print/share/delivery flows without breaking auth or ownership constraints.
- Task12 toggles are persisted per user (default on) and control only the intended client-facing copy in show/public/print.
- Settings/auth screens in Task12 scope (Profile, Invoice Settings, Login/Logout) match guardrails and updated UX patterns without introducing an auth-flow redesign.
- Tests updated/added for new flows and toggles; public views remain noindex and 403-safe.
- Docs (PLAN + onboarding/quick start later) updated after UX ships; changelog entries added per milestone.
