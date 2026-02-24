# Onboarding Wizard Spec (MS13 - UX ToDo #11)

## Draft Metadata
- Status: Draft (planning only, no implementation yet).
- This doc pass is spec-only (no controller/view/database implementation and no migration/test work).
- Remove this `Draft Metadata` section once the spec is implementation-ready.
- Keep Step 3 criteria intentionally flexible in this draft; finalize strict gating only in later implementation-ready spec passes.
- Open items to spec next:
  - Dismissed/completed state storage model and copy for user prompts.
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
- Canonical authenticated onboarding routes:
  - `GET /onboarding` (`onboarding.start`) resolves current progress and redirects to the first incomplete step.
  - `GET /onboarding/{step}` (`onboarding.step`) where `{step}` is one of `wallet`, `invoice`, or `deliver`.
  - `POST /onboarding/dismiss` (`onboarding.dismiss`) dismisses onboarding until the user explicitly reopens it.
  - `POST /onboarding/reopen` (`onboarding.reopen`) clears the dismissed state and restarts via `GET /onboarding`.
- Route guard/resume rules:
  - Users cannot skip ahead. Direct requests to later steps redirect to the earliest incomplete step.
  - If onboarding is already completed, `GET /onboarding` redirects to `/dashboard` with a completion status message.
  - If onboarding is dismissed, normal app entry points should not auto-redirect into onboarding until the user reopens it.
- Step page model (hybrid wrapper approach):
  - `GET /onboarding/{step}` renders a lightweight onboarding shell (progress, what to do next, primary CTA), not a duplicate of the underlying form or invoice UI.
  - Existing pages remain the source of truth for the actual work:
    - Wallet step -> `/wallet/settings`
    - Invoice step -> `/invoices/create`
    - Deliver step -> `/invoices/{invoice}`
  - When a user reaches one of those pages from onboarding, show a compact onboarding progress strip with the current step, success criteria, and a “Back to onboarding” link.
- Deliver step target invoice (resume behavior):
  - `deliver` needs an invoice context. The wizard may persist the most recent onboarding-created invoice ID for continuity.
  - `GET /onboarding/deliver` should prefer that stored invoice when it still exists, is owned by the authenticated user, and is not trashed.
  - Fallback: use the user's most recently created non-trashed invoice.
  - If no invoice exists, redirect to `/onboarding/invoice`.
  - Support `?invoice={id}` as an explicit target override, but only if the invoice is user-owned; otherwise ignore it and resolve using the rules above.
- Success redirects while onboarding is active:
  - Wallet saved successfully -> redirect to `GET /onboarding` (resolver advances to the next step).
  - Invoice created successfully -> redirect to `GET /onboarding/deliver?invoice={id}` so the wizard resumes with the created invoice context.
  - Delivery attempt logged on a public-enabled invoice -> redirect to `GET /onboarding` so completion is evaluated consistently in one place.

## Current Clarifications (2026-02-19)
- "Share enabled" means the invoice public link is enabled (`public_enabled=true`) so the public URL is active.
- If onboarding is dismissed, it should stay dismissed until the user intentionally reopens it.
- Reopen entry point should be available from the authenticated user dropdown.
