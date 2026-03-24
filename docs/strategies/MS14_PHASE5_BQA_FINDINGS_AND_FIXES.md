# MS14 Phase 5 Strategy - Browser QA Findings + Fixes

Status: Active during Browser QA. Log findings here as they surface; sequence the fix work after the session.
Parent phase strategy: [`docs/strategies/MS14_PHASE5_CORRECTION_TOOLING_SAFEGUARDS.md`](MS14_PHASE5_CORRECTION_TOOLING_SAFEGUARDS.md)
Canonical requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

This strategy is the running scratchpad for Phase 5 Browser QA findings. During the live session, capture the repro, expected behavior, and observed behavior here. After Browser QA ends, reshape this doc into the ordered fix sequence.

## Findings
1. [ ] Ignore validation loses scroll position and weakens required-field feedback
   - Surface: owner invoice show page, Scenario A on invoice `67` / `INV-0003`, payment row `39`.
   - Repro:
     1. Open invoice `67` and expand the `Ignore` confirmation for payment row `39`.
     2. Leave the reason empty and click `Confirm Ignore`.
   - Expected:
     1. The page stays anchored on the same payment row.
     2. The inline ignore form stays open with the typed state preserved.
     3. Focus lands on the required reason field and the validation error is obvious.
     4. Nothing about the row presentation implies the payment was ignored.
   - Actual:
     1. The page reloads near the top and loses scroll position.
     2. The payment row falls out of view.
     3. The missing required reason is easier to miss.
     4. The user can briefly think the ignore succeeded when it did not.

## Fix Sequence
- [ ] Flesh this out after Browser QA ends.
