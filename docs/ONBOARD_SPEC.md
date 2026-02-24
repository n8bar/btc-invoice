# Onboarding Wizard Spec (MS13 - UX ToDo #11)

## Draft Metadata
- Status: Draft (planning only, no implementation yet).
- This doc pass is spec-only (no controller/view/database implementation and no migration/test work).
- Remove this `Draft Metadata` section once the spec is implementation-ready.
- Keep Step 3 criteria intentionally flexible in this draft; finalize strict gating only in later implementation-ready spec passes.
- Open items to spec next:
  - Prompt copy for dismiss/reopen/completion states.
  - Exact empty-state placement on dashboard/invoices and mobile behavior.
  - Accessibility details (focus order, status announcements, keyboard flow).

Purpose: define the guided onboarding flow that helps a signed-in owner reach first invoice delivery without bypassing existing auth/policy checks.

## Scope (from UX Overhaul Task 11)
- Guide users through: connect wallet -> create invoice -> enable share + deliver.
- The flow links into existing wallet/invoice pages; it does not replace policy checks or controller authorization.
- The wizard can be dismissed or completed.
- Empty states (no wallet / no invoices) should point into the onboarding flow with clear CTAs.

## Wizard Steps
1. Connect wallet.
- Action: go to `/wallet/settings` and save a valid wallet account key.
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

## Route / URL Shape + Step Page Behavior (Draft Decision, 2026-02-23)
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

## Getting-Started State Storage Model (Draft Decision, 2026-02-23)
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

## Current Clarifications (2026-02-19)
- "Share enabled" means the invoice public link is enabled (`public_enabled=true`) so the public URL is active.
- If onboarding is dismissed, it should stay dismissed until the user intentionally reopens it.
- Reopen entry point should be available from the authenticated user dropdown.
