# Onboarding Wizard Spec (MS13 - UX ToDo #11)

Status: Draft (planning only, no implementation yet).

Purpose: define the guided onboarding flow that helps a signed-in owner reach first invoice delivery without bypassing existing auth/policy checks.

## Scope (from UX Overhaul Task 11)
- Guide users through: connect wallet -> create invoice -> enable share + deliver.
- The flow links into existing wallet/invoice pages; it does not replace policy checks or controller authorization.
- The wizard can be dismissed or completed.
- Empty states (no wallet / no invoices) should point into the onboarding flow with clear CTAs.

## Current Clarifications (2026-02-19)
- "Share enabled" means the invoice public link is enabled (`public_enabled=true`) so the public URL is active.
- If onboarding is dismissed, it should stay dismissed until the user intentionally reopens it.
- Reopen entry point should be available from the authenticated user dropdown.

## Out Of Scope (for this draft)
- No controller/view/database implementation in this step.
- No migration/test work yet.

## Open Items To Spec Next
- Completion criteria for step 3 (share enabled only vs. share enabled + at least one delivery).
- Route/URL shape for the wizard and resume behavior.
- Dismissed/completed state storage model and copy for user prompts.
- Exact empty-state placement on dashboard/invoices and mobile behavior.
- Accessibility details (focus order, status announcements, keyboard flow).
