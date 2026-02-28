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

## Task 11 Pass1 Findings Strategy (Implementation Order)
Goal: address the current Pass1 findings in a controlled sequence with small, testable increments.

- [x] 1. Orientation entry (Finding 1)
- Add a dedicated welcome route/view for onboarding entry (internal orientation step).
- Route split:
  - new registrants enter via welcome,
  - returning incomplete logins enter via resolver-first actionable step.
- Keep copy concise: what CryptoZing is, 3-step framing, rough "minutes" expectation, single next action.
- Deliverable check: new registrants see welcome first; returning incomplete users land on the correct next step without intro-loop friction.

- [x] 2. Back-link clarity + placement (Finding 2)
- Update getting-started card/back-link labels to destination-aware wording.
- Position the back link in the card top-right area where applicable.
- Keep labels short and specific to actual destination (`Back to welcome`, `Back to [previous step]`).
- Deliverable check: no generic "Back to getting started" where a better destination label exists.

- [x] 3. Wallet helper discoverability (Finding 3)
- Keep current helper location/order and collapsed default.
- Add onboarding-only visual emphasis (static, non-animated first pass) so the helper reads as relevant.
- Validate dark-mode contrast during this pass, with specific attention to indigo-on-slate and dark-on-dark combinations.
- Deliverable check: helper is easier to spot in onboarding mode without adding visual noise for normal mode.

- [x] 4. Dashboard prompt controls (Finding 4)
- Add temporary close control (`X`) on the dashboard prompt (hide until reload only).
- Add persistent `Hide for now` action with confirmation tied to existing dismiss endpoint/state.
- Preserve `Resume getting started` as primary CTA.
- Deliverable check: users can dismiss from dashboard without entering the wizard, and can still resume later.

- [x] 5. Zero-client invoice gate (Finding 5)
- When no clients exist, replace invoice form with a focused create-client step on `/invoices/create`.
- Reuse existing client store validation and shared client fields partial (no duplicate business logic).
- After create, return to invoice create (preserving onboarding context).
- Deliverable check: onboarding step 2 is actionable even when client count starts at zero.

- [x] 6. Guided focus emphasis (Finding 6)
- Add onboarding-only, subtle glow emphasis to primary action zones:
  - wallet key input + save action (and helper if needed for discoverability),
  - invoice create primary submit,
  - invoice deliver actions (`Enable public link` + send action).
- Keep emphasis static/subtle for pass1 (no looping animation).
- Deliverable check: QA can identify required action areas quickly in each step.

- [x] 7. Replay mode for completed users (Finding 7)
- Add a persistent replay mode so completed users can intentionally run Getting Started again without old data auto-completing every step.
- Introduce replay state keyed by a `replay_started_at` timestamp (must persist across logout).
- Step rules in replay mode:
  - Wallet step: verify current wallet settings (no new xpub required).
  - Invoice step: requires an invoice created at/after `replay_started_at`.
  - Deliver step: requires share/send activity at/after `replay_started_at`.
- Replay entry behavior from user menu:
  - If Getting Started was dismissed, reopen starts immediately.
  - If Getting Started was completed (not dismissed), show a confirmation that includes the recorded completion date before starting replay.
- Keep existing business data intact; replay resets onboarding guidance state, not wallets/invoices/clients.
- Deliverable check: completed users can intentionally restart and progress through a meaningful guided rerun.

- [ ] 8. New browser QA pass (coverage + gap finder)
- Run a new, focused QA set that re-checks core flow behavior and targets blind spots from earlier passes.
- Suggested execution order (about 30-45 minutes total):
  - Pass A (10-15m): Core regressions
    - [x] New registrant lands on welcome entry and can complete all steps end-to-end.
    - [x] Incomplete returning user lands on resolver-first actionable step (not forced to welcome).
    - Replay-started user enters wallet step first and does not skip directly to dashboard.
  - Pass B (10-15m): Replay boundary checks
    - Pre-replay artifacts do not auto-complete replay invoice/deliver steps.
    - New replay invoice advances to deliver step.
    - Replay completion requires replay-era delivery activity and then closes with completion status.
    - Replay state survives logout/login and resumes at the expected step.
  - Pass C (5-10m): Deliver-step targeting correctness
    - Multiple eligible draft invoices: Change selector swaps target correctly.
    - Invalid or stale `?invoice=` falls back safely.
    - Non-eligible invoices (sent/void/trashed/other-user) cannot become target invoice.
  - Pass D (5-10m): UX/a11y quick sweep
    - Light + dark mode contrast remains acceptable in step cards and highlighted actions.
    - Desktop + narrower screens avoid horizontal overflow in step shell and progress strip.
    - One keyboard-only run confirms CTA path is reachable without focus traps.
- Evidence to capture during QA:
  - User + scenario tested, exact route hit, expected vs actual result, and whether issue is reproducible.
  - For regressions, include a short note on whether impact is block/major/minor.
- Exit criteria:
  - Mark this item complete only when Passes A-D are done and any new issues are logged in `docs/strategies/TASK11_UX_ENGINEERING_PASS.md`.

## Notes / Risks
- Keep underlying forms/pages as source of truth; avoid duplicating field validation UI in step shells.
- Avoid global middleware interception in v1 to prevent route allowlist drift and redirect loops.
- Re-check accessibility requirements in `docs/ONBOARD_SPEC.md` before final UI polish (focus order, status announcements, keyboard reachability).
