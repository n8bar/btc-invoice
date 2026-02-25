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

### Finding 3: Dark-mode contrast issues in onboarding labels (including indigo-on-slate)
- Observation:
  - The wallet-page onboarding label text (`Step 1 of 3: Connect wallet`) has poor contrast in dark mode (observed as indigo-on-slate / dark-on-dark).
  - Similar contrast issues may exist in other onboarding labels across the step shell and progress strips.
- Direction:
  - Fix the wallet-page label contrast regression.
  - Perform a small audit of onboarding labels in dark mode for dark-on-dark combinations generally, with explicit attention to indigo-on-slate pairings.
- Scope targets:
  - Getting-started step shell labels and progress indicators.
  - Progress-strip labels on Wallet Settings / New Invoice / Invoice Show.
- Constraints:
  - Preserve visual hierarchy (current step vs completed vs upcoming).
  - Favor readable contrast over subtle color styling.

### Finding 4: Wallet key helper discoverability in onboarding context (collapsed + emphasized variant)
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
