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

### Finding 1: Missing orientation before first wallet CTA
- Observation:
  - On first login as a new/incomplete user, `/getting-started/wallet` leads visually with the CTA (`Open Wallet Settings`) before clearly explaining what CryptoZing is, what the flow will do, and how long it will take.
  - The page feels dense and guessy for a first-time user, even if technically competent.
- Direction:
  - Add an internal-only `Orientation Step 0` layer at the top of the first onboarding screen (wallet step) that introduces the flow before the step action.
  - Do not mention or label this as “Step 0” to the user.
- User-facing outcome we want:
  - A short welcome + expectation-setting message (what CryptoZing is / what onboarding accomplishes).
  - A clear 3-step framing and rough time estimate (phrased as an estimate, not a guarantee).
  - A clean transition into the wallet step action (“what to do now”).
- Constraints:
  - Keep the intro concise and no-nonsense.
  - Do not add feature marketing blocks or heavy product explanation.
  - Preserve the existing step shell flow and routing; this is primarily copy/information hierarchy work.
