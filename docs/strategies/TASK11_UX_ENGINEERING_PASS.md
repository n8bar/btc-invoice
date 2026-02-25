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
