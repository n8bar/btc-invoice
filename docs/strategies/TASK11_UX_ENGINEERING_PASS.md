# Task 11 UX Engineering Pass (Working Doc)

Status: Advisory UX refinement pass for Task 11 based on browser QA findings.

This document is a temporary working strategy. It is not a source of truth like `docs/ONBOARD_SPEC.md`, `docs/UX_OVERHAUL_SPEC.md`, `docs/PLAN.md`, or `docs/CHANGELOG.md`, and may be deleted after Task 11 follow-up work ships.

## Purpose
- Collect browser-QA findings and address them in a focused Task 11 UX refinement pass.
- Keep implementation-pass strategy (`docs/strategies/TASK11_GETTING_STARTED_STRATEGY.md`) separate from post-implementation usability/design iteration notes.
- Allow incremental additions as findings are discovered.

## Working Rules
- Internal naming is allowed for design/implementation discussion (for example `Orientation Step 0`).
- Do not expose internal labels (such as `Step 0`) in user-facing UI copy.
- Prefer high-impact clarity improvements first (copy hierarchy, orientation, action sequencing) before broad visual redesign.
- Log findings first, then batch related fixes into a coherent pass when practical.

## Findings

### Finding 1: Missing orientation screen/state before first wallet action (internal `Orientation Step 0`)
- Observation:
  - On first login as a new/incomplete user, `/getting-started/wallet` leads visually with the CTA (`Open Wallet Settings`) before clearly explaining what CryptoZing is, what the flow will do, and how long it will take.
  - The page feels dense and guessy for a first-time user, even if technically competent.
- Direction:
  - Add an internal-only `Orientation Step 0` as a dedicated welcome page/route before the wallet step.
  - Do not mention or label this as “Step 0” to the user.
- Pass shape (locked for this finding):
  - Use a dedicated route/page for the welcome experience (not an in-page panel inside `/getting-started/wallet`).
  - Keep the welcome page intentionally minimal with no UI clutter or extra explanatory copy that distracts from the next action.
- User-facing outcome we want:
  - A short welcome + expectation-setting message (what CryptoZing is / what onboarding accomplishes).
  - A clear 3-step framing.
  - A very rough time expectation (for example: “You could be sending your first invoice in minutes.”).
  - A clean, obvious transition into the wallet step action (“what to do now”).
- Constraints:
  - Keep the intro concise and no-nonsense.
  - Do not add feature marketing blocks or heavy product explanation.
  - Favor a focused welcome page over dense step-shell content on the first screen.

### Finding 2: Wallet Settings onboarding card back-link labeling and placement
- Observation:
  - In `getting_started=1` mode on Wallet Settings, the progress-strip card title (`GETTING STARTED`) is acceptable, but the button label (`Back to getting started`) is vague/redundant.
  - The back button placement also competes with the main content instead of reading like card-level navigation.
- Direction:
  - Move the onboarding back button to the top-right area of the getting-started card.
  - Use destination-aware labels instead of a generic “Back to getting started.”
- Current preferred labels (subject to later step QA):
  - Wallet Settings onboarding context: `Back to welcome` (points to the planned dedicated welcome route from Finding 1).
  - Subsequent steps/pages: prefer `Back to [previous step/screen]` wording rather than one universal label.
- Constraints:
  - Keep label text short and scannable.
  - Do not imply a destination that does not actually exist for that page/context.

### Finding 3: Wallet key helper discoverability in onboarding context (collapsed + emphasized variant)
- Observation:
  - On Wallet Settings during getting-started, the `Where do I find this?` helper is easy to miss even though it is highly relevant to first-time users.
  - The current placement/collapsed presentation is fine for returning users, but onboarding users need a stronger cue.
- Direction (preferred variant):
  - Keep the helper card in its current location/order.
  - Keep the helper collapsed by default.
  - Add onboarding-only discoverability emphasis in `getting_started=1` mode.
- Emphasis ideas (priority order):
  - Stronger visual cue (accent border/background/badge such as “Recommended for setup”).
  - Clearer summary/label text signaling relevance to first-time setup.
  - Optional subtle motion only if needed after static emphasis is tested.
- Motion constraints (if used):
  - One-time/subtle attention cue only (no constant looping animation).
  - Respect `prefers-reduced-motion`.
  - Avoid drawing attention away from the primary form controls once the page has settled.

### Finding 4: Dashboard getting-started prompt lacks inline dismiss affordance
- Observation:
  - The dashboard getting-started CTA box is a soft prompt, but it currently has no inline way to dismiss/hide the prompt from that surface.
  - Users must re-enter the getting-started flow to access dismiss, which makes a “soft” prompt feel more forceful than intended.
- Direction:
  - Add two distinct affordances on the dashboard prompt while keeping the primary resume CTA:
    - a top-right `X` close control that hides the prompt temporarily (client-side only; returns on next page reload)
    - a persistent `Hide for now` action that uses the existing getting-started dismiss behavior
  - `Hide for now` should confirm before submitting `POST /getting-started/dismiss` and remain reversible from the user menu.
- Constraints:
  - Keep `Resume getting started` as the clear primary CTA.
  - Keep `Hide for now` visually secondary.
  - Make the `X` control clearly temporary (not a persistent dismiss).
  - Avoid cluttering the prompt; maintain simple hierarchy despite adding two secondary controls.

### Finding 5: Invoice create has a hidden client prerequisite for zero-client users
- Observation:
  - After wallet setup (including getting-started Step 2 handoff), users can be sent to `/invoices/create` with no clients in the system.
  - The invoice form starts with the `Client` field, but there is no clear guidance or inline path for users who have not created a client yet.
  - This is both a general UX gap (invoice create) and a getting-started UX gap (Step 2 implies invoice creation is immediately actionable).
- Direction (preferred shape):
  - When the client list is empty, gate the invoice-create page and show a focused inline `Create client` experience with a short explanation.
  - Do not show the invoice form and client-create form at the same time.
  - After client creation, redirect back to `/invoices/create` (preserving onboarding context such as `getting_started=1` when present), then show the invoice form.
- Reuse / implementation guidance:
  - Reuse the existing `clients.store` action and validation path (no duplicate client business logic).
  - Extract shared client form fields into a reusable partial/component so `clients/create` and the invoice zero-client gate UI share markup.
  - Support a safe return target (for example, a validated internal `return_to` value) so client creation can return users to invoice create without open-redirect risk.
- Constraints:
  - Never render both forms as active primary actions on the same screen at the same time.
  - Keep the zero-client state focused and instructional, not modal-heavy.
  - This finding does not currently recommend clientless invoices, auto-created self-clients, or popup flows.

### Finding 6: Primary action zones are hard to locate across onboarding steps
- Observation:
  - During browser QA, the core action elements in each step can be hard to spot quickly, which increases friction and hesitation.
  - Users may understand the instruction text but still spend time scanning for the exact input/button cluster to act on.
- Direction:
  - Add onboarding-only visual emphasis ("guided focus" glow) around primary action zones when in getting-started context.
  - Keep emphasis scoped to primary action(s), not every form field.
- Step targets:
  - Step 1 (`/wallet/settings?getting_started=1`): wallet key input + `Save wallet`, and include `Where do I find this?` in the highlight scope if it will not otherwise get enough attention.
  - Step 2 (`/invoices/create?getting_started=1`): primary submit action (`Save`), while keeping required-field asterisks as-is.
  - Step 3 (`/invoices/{invoice}?getting_started=1`): `Enable public link` and send/delivery primary action element.
- Constraints:
  - Avoid visual overload; this is directional emphasis, not full-page highlighting.
  - Keep treatment subtle and consistent (prefer static glow/border over constant animation).
  - Respect accessibility expectations (`prefers-reduced-motion`) if any motion cue is later introduced.

### Finding 7: Completed users cannot intentionally restart onboarding from account menu
- Observation:
  - Selecting `Getting started` from the account menu as a user who already completed onboarding currently returns a success-style banner indicating getting started is completed.
  - The flow does not provide a clear, immediate way for that user to intentionally reset/re-run the onboarding sequence.
  - This creates a dead-end for users who want a guided refresher.
- Direction:
  - For completed users, provide an explicit restart affordance rather than only showing informational status.
  - Keep the current completed state as default; restart should be intentional and user-triggered.
- Preferred interaction shape:
  - When a completed user chooses `Getting started`, show a compact banner/card with:
    - plain-language completion state
    - a secondary `Cancel`/dismiss action
    - a primary `Start over` (or `Run setup again`) action
  - On confirm/start-over:
    - clear completion timestamp
    - clear dismissed state
    - redirect to onboarding welcome entry (internal orientation route), not mid-flow
- Data/logic expectations:
  - Restart should reset onboarding progress state only; it must not delete wallet, invoices, deliveries, or clients.
  - Step derivation remains data-driven from existing records; restart only re-enables the guided flow and completion lifecycle.
  - Keep this behavior idempotent and safe to trigger multiple times.
- Constraints:
  - Do not auto-reset on simple menu click; require explicit user confirmation/action.
  - Keep copy concise and non-alarming (this is a guide reset, not data reset).
  - Preserve current security model (auth-only action, CSRF-protected POST).
- Acceptance checks:
  - Completed user can intentionally restart from account menu in <=2 clicks after selecting `Getting started`.
  - Restart sends user to onboarding welcome and shows progress as active/incomplete.
  - Existing business data remains unchanged after restart.
  - If user cancels restart, completion state remains intact.
