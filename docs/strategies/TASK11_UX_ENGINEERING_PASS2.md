# Task 11 UX Engineering Pass2 (Working Doc)

Status: Advisory UX refinement pass for Task 11 follow-up findings.

This document is a temporary working strategy. It is not a source of truth like `docs/ONBOARD_SPEC.md`, `docs/UX_OVERHAUL_SPEC.md`, `docs/PLAN.md`, or `docs/CHANGELOG.md`, and may be deleted after Task 11 follow-up work ships.

## Purpose
- Capture post-pass findings while current UXInspections continue without scope churn.
- Keep new findings isolated so implementation can be batched intentionally.

## Findings

### Finding 1: Non-replay Step 2 should require a deliver-eligible draft invoice
- Observation:
  - In non-replay onboarding, Step 2 currently completes when any invoice exists.
  - Step 3 target selection is draft-only, so users can arrive at Step 3 with no eligible target invoice (for example when a newly created invoice auto-transitions to `paid` due to reused-wallet history).
  - This can delay discovery of wallet-reuse issues and create avoidable confusion.
- Direction:
  - Align non-replay Step 2 gating with deliver targeting: require at least one eligible draft invoice before Step 2 is considered complete.
  - Keep Step 3 completion criteria action-based (public link enabled + delivery attempt), not draft-status-based.
- Optional guard (approved direction):
  - After onboarding invoice creation, if the created invoice is immediately non-draft, show an explicit warning that reused-wallet activity likely triggered early payment detection and instruct the user to create a new draft invoice.
- Desired outcome:
  - Users hit the collision effect earlier (at Step 2) with clearer guidance.
  - Support escalation happens sooner, with less “why is Step 3 blocked?” ambiguity.
- Scope status:
  - Logged for Pass2 implementation; not required to complete the in-flight UXInspections.
