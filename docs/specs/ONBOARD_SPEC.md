# Onboarding Wizard Spec (MS13 - UX ToDo #11)

Purpose: define the guided onboarding flow that helps a signed-in owner reach first invoice delivery without bypassing existing auth/policy checks.

## Scope (from UX Overhaul Task 11)
- Guide users through: connect wallet -> create invoice -> enable share + deliver.
- The flow links into existing wallet/invoice pages; it does not replace policy checks or controller authorization.
- The wizard can be dismissed or completed.
- Empty states (no wallet / no invoices) should point into the onboarding flow with clear CTAs.
- The wallet step should reinforce the dedicated receiving-account requirement in plain language and link into the Helpful Notes explainer when that content ships.

## Wizard Steps
1. Connect wallet.
- Action: go to `/wallet/settings`, save a valid dedicated wallet account key, and review the dedicated-account guidance / Helpful Notes explainer if needed.
- Completion signal: the authenticated user has a wallet setting.

2. Create first invoice.
- Action: go to `/invoices/create` and save an invoice.
- Completion signal: the authenticated user has at least one non-trashed invoice.

3. Enable share + deliver.
- Action: from invoice show, enable the public link and send the invoice email.
- Completion signal: at least one user-owned invoice has `public_enabled=true` and at least one delivery log attempt.
- Self-send test philosophy (draft guidance):
  - Allow sending the first invoice to the owner email.
  - Lightly encourage a self-send as an optional confidence check.
  - Do not require self-send to complete onboarding.
  - If recipient equals owner email, mark/log it as a test delivery when that behavior is implemented.

If any step proves too broad during implementation, split it into explicit substeps without changing these completion outcomes.

## Route / URL Shape + Step Page Behavior
- Canonical authenticated routes for the getting-started flow:
  - `GET /getting-started` (`getting-started.start`) resolves current progress and redirects to the first incomplete step.
  - `GET /getting-started/{step}` (`getting-started.step`) where `{step}` is one of `wallet`, `invoice`, or `deliver`.
  - `POST /getting-started/dismiss` (`getting-started.dismiss`) dismisses getting-started until the user explicitly reopens it.
  - `POST /getting-started/reopen` (`getting-started.reopen`) clears the dismissed state and restarts via `GET /getting-started`.
- Route guard/resume rules:
  - Users cannot skip ahead. Direct requests to later steps redirect to the earliest incomplete step.
  - If getting-started is already completed, `GET /getting-started` redirects to `/dashboard` with a completion status message.
  - If getting-started is dismissed, normal app entry points should not auto-redirect into getting-started until the user reopens it.
- Step page model (hybrid wrapper approach):
  - `GET /getting-started/{step}` renders a lightweight getting-started shell (progress, what to do next, primary CTA), not a duplicate of the underlying form or invoice UI.
  - Existing pages remain the source of truth for the actual work:
    - Wallet step -> `/wallet/settings`
    - Invoice step -> `/invoices/create`
    - Deliver step -> `/invoices/{invoice}`
  - When a user reaches one of those pages from getting-started, show a compact progress strip with the current step, success criteria, and a “Back to getting started” link.
- Deliver step target invoice (resume behavior):
  - `deliver` needs an invoice context.
  - `GET /getting-started/deliver` should prefer a valid user-owned `?invoice={id}` override when provided.
  - Otherwise, use the user's most recently created non-trashed invoice.
  - If no invoice exists, redirect to `/getting-started/invoice`.
  - Ignore invalid/foreign/trashed `?invoice={id}` values and resolve using the fallback rules above.
- Success redirects while getting-started is active:
  - Wallet saved successfully -> redirect to `GET /getting-started` (resolver advances to the next step).
  - Invoice created successfully -> redirect to `GET /getting-started/deliver?invoice={id}` so the flow resumes with the created invoice context.
  - Delivery attempt logged on a public-enabled invoice -> redirect to `GET /getting-started` so completion is evaluated consistently in one place.

## Getting-Started State Storage Model
- Purpose of this model:
  - Store only user intent/state that cannot be safely derived from existing business data.
  - Derive step progress from real app data so the flow stays truthful even if users complete steps outside the getting-started entry point.
- Step progress is derived (not stored as step booleans):
  - Step 1 complete when the authenticated user has a wallet setting.
  - Step 2 complete when the authenticated user has at least one non-trashed invoice.
  - Step 3 complete when at least one user-owned invoice has `public_enabled=true` and at least one delivery log attempt.
- Persisted user fields for the flow (minimal v1):
  - `getting_started_completed_at` (`timestamp`, nullable)
  - `getting_started_dismissed` (`boolean`, default `false`)
- "Done" for auto-prompting (two completion paths):
  - The flow is considered done when `getting_started_completed_at != null`.
  - `getting_started_dismissed` records how it was completed:
    - `false` = completed by finishing the steps
    - `true` = completed by dismissing the flow
- Auto-show rule:
  - Auto-show getting-started only when `getting_started_completed_at == null`.
- State update rules (keep behavior predictable):
  - Completion sets `getting_started_completed_at` and clears `getting_started_dismissed` to `false`.
  - Dismiss sets `getting_started_completed_at` and sets `getting_started_dismissed` to `true`.
  - Reopen clears both `getting_started_completed_at` and `getting_started_dismissed`.
- Why this stays intentionally simple:
  - No per-step state storage to backfill or reconcile.
  - No "last invoice" pointer persisted in v1; deliver-step resume uses `?invoice={id}` or falls back to the user's latest invoice.
  - Auto-show logic checks one field (`getting_started_completed_at`) while `getting_started_dismissed` remains useful metadata about how the flow was completed.
  - If product needs richer analytics/re-entry behavior later, expand from this baseline instead of starting with a generalized flow-state table.

## Copy Examples
- Examples only (not requirements). Implementation may shorten/refine.
- Dismiss dialog:
  - Title: `Hide getting started?`
  - Body: `You can reopen it from the account menu.`
  - Primary action: `Hide for now`
  - Secondary action: `Continue`
- Dismiss success:
  - `Getting started hidden.`
- Reopen / menu:
  - User menu item: `Getting started`
  - CTA/button: `Resume getting started`
- Completion success:
  - `Getting started complete.`
- Step shell examples (what the getting-started page shows):
  - Wallet title: `Connect your wallet`
  - Wallet body: `Add a dedicated wallet account key so CryptoZing can generate a payment address for each invoice. Need help? Review why CryptoZing needs a dedicated receiving account key.`
  - Wallet CTA: `Open wallet settings`
  - Invoice title: `Create your first invoice`
  - Invoice body: `Create an invoice to continue getting started.`
  - Invoice CTA: `Create invoice`
  - Deliver title: `Share and send your invoice`
  - Deliver body: `Enable the public link, then send the invoice email.`
  - Deliver CTA: `Open invoice`

## Accessibility Details
- Keep the getting-started flow simple:
  - Prefer standard links, buttons, forms, and full-page navigation over custom widgets.
  - Avoid custom keyboard shortcuts; all actions should work with normal keyboard navigation.
- Step shell page structure (applies to `GET /getting-started/{step}`):
  - One clear page heading.
  - Visible text progress (for example, `Step 2 of 3`) and current step name.
  - Primary CTA and dismiss action in a logical reading/tab order.
  - Do not rely on color alone to indicate the current step or status.
- Focus order:
  - Keep DOM order aligned with visual order.
  - Do not steal focus on load unless needed for error handling.
  - When validation fails on underlying wallet/invoice pages, preserve existing form behavior (focus first error, preserve input) per `docs/UX_GUARDRAILS.md`.
  - If a custom dismiss dialog is used, move focus into the dialog, keep focus inside while open, and return focus to the trigger when closed.
- Keyboard flow:
  - Every getting-started action (open step CTA, dismiss, resume, back-to-getting-started link) must be keyboard reachable.
  - Interactive controls must have visible focus styles.
  - The progress strip on wallet/invoice/invoice-show pages must not block or trap keyboard navigation for the underlying page.
- Status announcements:
  - Dismiss success, completion success, and similar non-error updates should be rendered in a status region announced politely (for example `role="status"` / `aria-live="polite"`).
  - Errors should use error/alert semantics (for example `role="alert"` where appropriate).
  - Keep status messages near the top of the main content so they are easy to notice visually and via screen readers.
  - Do not duplicate validation messages in both the step shell and the underlying form; the form remains the source of truth for field-level errors.
- Practical rule for this task:
  - If a fancier interaction makes accessibility harder, use the simpler interaction.

## Current Clarifications
- "Share enabled" means the invoice public link is enabled (`public_enabled=true`) so the public URL is active.
- If onboarding is dismissed, it should stay dismissed until the user intentionally reopens it.
- Reopen entry point should be available from the authenticated user dropdown.
- Onboarding reinforces the dedicated receiving-account requirement but does not hard-block solely because wallet-risk guidance or unsupported-state warnings appear.
