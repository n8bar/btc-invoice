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

## Notes / Risks
- Keep underlying forms/pages as source of truth; avoid duplicating field validation UI in step shells.
- Avoid global middleware interception in v1 to prevent route allowlist drift and redirect loops.
- Re-check accessibility requirements in `docs/ONBOARD_SPEC.md` before final UI polish (focus order, status announcements, keyboard reachability).
