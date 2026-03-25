# MS14 Phase 5 Strategy - Browser QA Findings + Fixes

Status: Active after Browser QA. Use this doc to sequence the Phase 5 follow-up fixes.
Parent phase strategy: [`docs/strategies/MS14_PHASE5_CORRECTION_TOOLING_SAFEGUARDS.md`](MS14_PHASE5_CORRECTION_TOOLING_SAFEGUARDS.md)
Canonical requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

This strategy is the active Phase 5 follow-up doc for issues found during Browser QA. Keep the findings list intact and turn the fix sequence below into the ordered follow-up implementation plan.

## Findings
1. Ignore validation loses scroll position and weakens required-field feedback
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

2. Reattribution validation loses scroll position and makes the missing reason feel accepted
   - Surface: owner invoice show page, Scenario C on source invoice `69` / `INV-0005`, `30,000 sats` / `$21.06` payment detected `Mon, Mar 23, 2026 11:41 PM`.
   - Repro:
     1. Open invoice `69` and start the reattribution flow for the `30,000 sats` / `$21.06` payment.
     2. Choose destination invoice `70` / `INV-0006`.
     3. Leave the reattribution reason empty and submit.
   - Expected:
     1. The page stays anchored on the same payment row.
     2. The correction panel stays open with the selected destination preserved.
     3. Focus lands on the required reason field and the validation error is obvious.
     4. Nothing about the row presentation implies the reattribution succeeded.
   - Actual:
     1. The page reloads and loses scroll position.
     2. The payment row and correction panel fall out of view.
     3. The user can think the reattribution succeeded because the failed validation is no longer in context.

3. Manual adjustment rows have no owner-facing `oops` path
   - Surface: owner invoice show page, any manual adjustment row in payment history.
   - Repro:
     1. Add a manual adjustment row from the existing `Manual adjustments` form.
     2. Review that row in payment history and try to correct it after realizing the amount/direction was wrong.
   - Expected:
     1. Manual adjustment rows expose the append-only undo path inline.
     2. The owner can click `Reverse` / `adjustment` to reveal `Confirm` / `reverse` / `entry`.
     3. Confirming creates an equal-and-opposite manual adjustment row with note `reversal of {txid}` while preserving the original row in history.
   - Actual:
     1. Manual adjustment rows show no reversal affordance.
     2. The only shipped adjustment action is creation, so an owner cannot practically say `oops` after recording the wrong adjustment.

## Fix Sequence
- [ ] Flesh this out for the follow-up Phase 5 fixes.
