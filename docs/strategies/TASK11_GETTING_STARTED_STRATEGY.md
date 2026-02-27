# Task 11 Getting-Started Strategy (Working Doc)

Status: Advisory implementation strategy for UX Task 11.

This document is a temporary working plan. It is not a source of truth like `docs/ONBOARD_SPEC.md`, `docs/UX_OVERHAUL_SPEC.md`, `docs/PLAN.md`, or `docs/CHANGELOG.md`, and may be deleted after Task 11 ships.

## Canonical Inputs
- Product/flow requirements: `docs/ONBOARD_SPEC.md`
- UX milestone tracking: `docs/UX_OVERHAUL_SPEC.md`
- RC roadmap status: `docs/PLAN.md`

## Locked v1 Decisions
- Scope this branch/PR to Task 11 only.
- Use a derived-progress service (`App\\Services\\GettingStartedFlow`) instead of storing per-step booleans.
- Auto-show behavior is v1-limited to:
  - hard redirect after login when onboarding is incomplete and not dismissed
  - soft prompts (dashboard/empty states + user menu resume)
  - no global route-interception middleware

## Implementation Phases
1. Flow foundation (data + service)
- Add user fields:
  - `getting_started_completed_at` (nullable timestamp)
  - `getting_started_dismissed` (boolean default `false`)
- Update `App\\Models\\User` fillable/casts/helpers for onboarding state.
- Build `App\\Services\\GettingStartedFlow` to:
  - derive step completion from wallet/invoices/delivery logs
  - resolve earliest incomplete step
  - resolve deliver-step invoice context (`?invoice=` override + latest owned fallback)
  - mark dismiss/complete/reopen

2. Routes + controller + step shell
- Add auth routes:
  - `GET /getting-started`
  - `GET /getting-started/{step}`
  - `POST /getting-started/dismiss`
  - `POST /getting-started/reopen`
- Add `GettingStartedController` with:
  - resolver redirect (`start`)
  - no-skip step guard (`step`)
  - dismiss/reopen handlers
- Add lightweight step-shell Blade view (progress text, CTA, dismiss).

3. Progress strip + resume entry points
- Add compact progress-strip partial for underlying pages:
  - `wallet/settings`
  - `invoices/create`
  - `invoices/{invoice}` (deliver step target)
- Add "Getting started" / "Resume getting started" entry to desktop + mobile user menu.

4. Success redirect hooks (when in getting-started context)
- Wallet save success -> resolver (`/getting-started`)
- Invoice create success -> `/getting-started/deliver?invoice={id}`
- Delivery attempt success -> resolver (`/getting-started`)
- Use an explicit request context flag (for example `getting_started=1`) instead of referrer detection.

5. Auto-show + soft prompts (v1)
- Login redirect into getting-started when onboarding is incomplete.
- Dashboard CTA/banner (soft prompt only).
- Invoices empty-state CTA to getting-started.
- Respect dismiss state: no auto-redirects/prompts that force users back in after dismiss until reopen.

6. Test coverage
- New feature suite for getting-started flow routing/state:
  - resolver, no-skip, deliver invoice resolution, dismiss/reopen, completion
- Extend integration tests for:
  - login redirect behavior
  - wallet save redirect in onboarding context
  - invoice create redirect in onboarding context
  - delivery attempt redirect/completion in onboarding context
  - progress strip visibility on wallet/create/show pages

7. Browser QA (manual owner pass, efficient but thorough)
- Goal: verify real browser flow UX, not just server behavior.
- Recommended order:
  - run the Core path first (high confidence / fastest signal)
  - run Edge/guard checks second (catch regressions without a full matrix)

### Core path (must-run)
- Happy-path onboarding with an account that already has at least one client:
  - Log in as an incomplete user -> confirm redirect to `/getting-started` (resolver) and first step shell.
  - Step 1 (`wallet`): CTA opens Wallet Settings with progress strip + “Back to getting started”.
  - Save wallet from getting-started context -> confirm redirect back to wizard/resolver (advances to step 2).
  - Step 2 (`invoice`): CTA opens New Invoice with progress strip.
  - Create invoice from getting-started context -> confirm redirect to deliver step with the created invoice context.
  - Step 3 (`deliver`): CTA opens the invoice show page with progress strip.
  - Enable public link, then send invoice email -> confirm redirect to resolver and final completion redirect to dashboard.
  - Confirm completion status message appears on dashboard and onboarding prompt is no longer shown.
- Normal navigation after completion:
  - Open Wallet Settings / Invoices Create / Invoice Show normally (not from wizard) -> progress strip should not appear.

### Edge / guard checks (high-value, short)
- Skip-ahead guard:
  - Manually visit `/getting-started/deliver` on a fresh/incomplete account -> confirm redirect to earliest incomplete step.
- Dismiss / reopen:
  - Use “Hide getting started” on a step shell -> confirm dashboard status and no forced re-entry.
  - Log out + log back in -> confirm no auto-redirect while dismissed.
  - Reopen from user menu -> confirm flow restarts/resumes via `/getting-started`.
- Deliver-step resume selection:
  - With multiple invoices, visit `/getting-started/deliver?invoice={id}` for a valid owned invoice -> confirm that invoice is opened.
  - Try an invalid/stale `?invoice=` value -> confirm fallback behavior (latest valid owned invoice or invoice step if none).
- Partial-progress resume:
  - Complete wallet + invoice steps only, then leave the flow.
  - Return via menu/dashboard CTA -> confirm resolver lands on deliver step (not wallet/invoice).

### UX / accessibility quick checks (short)
- Keyboard-only smoke test on step shell:
  - Tab order reaches progress/CTA/dismiss controls in a sensible order.
  - Visible focus styles are present on CTA, dismiss, and back links.
- Narrow-screen sanity (mobile width):
  - Step shell content and progress cards do not cause page-level horizontal overflow.
  - Progress strip wraps cleanly on wallet/invoice pages.
- Status messaging:
  - Wallet save / invoice create / delivery completion messages are visible near top of content and easy to notice.

## UX Engineering Pass (Task 11)
- Browser-QA findings and follow-up UX refinement items now live in:
  - `docs/strategies/TASK11_UX_ENGINEERING_PASS.md`
- Keep this file focused on the implementation pass (v1 behavior + test plan).

## Notes / Risks
- Keep underlying forms/pages as source of truth; avoid duplicating field validation UI in step shells.
- Avoid global middleware interception in v1 to prevent route allowlist drift and redirect loops.
- Re-check accessibility requirements in `docs/ONBOARD_SPEC.md` before final UI polish (focus order, status announcements, keyboard reachability).
