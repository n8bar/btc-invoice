# MS14 Phase 5 Strategy - Browser QA Findings + Fixes

Status: Active after Browser QA. The follow-up fixes are implemented on this branch; remaining work is the targeted Browser QA rerun.
Parent phase strategy: [`docs/strategies/MS14_PHASE5_CORRECTION_TOOLING_SAFEGUARDS.md`](MS14_PHASE5_CORRECTION_TOOLING_SAFEGUARDS.md)
Canonical requirements: [`docs/specs/PAYMENT_CORRECTIONS.md`](../specs/PAYMENT_CORRECTIONS.md)

This strategy is the active Phase 5 follow-up doc for issues found during Browser QA. Keep the findings list intact, track what shipped in the fix sequence below, and use the remaining verification items for the targeted rerun.

## 1. Findings
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

4. Reattributed payments have no clean inline undo path
   - Surface: owner invoice show page, any reattributed payment row in payment history.
   - Repro:
     1. Reattribute a payment from a source invoice to a destination invoice.
     2. Try to undo that reattribution cleanly from the correction UI.
   - Expected:
     1. A reattributed payment exposes a clear inline way to return the active accounting destination to the source invoice.
     2. The undo path does not depend on choosing a fake destination or guessing at a no-op.
     3. If the current invoice is the right destination again, the correction UI makes that obvious instead of hiding the option.
   - Actual:
     1. There is no clear undo action for a reattributed payment.
     2. The current/source invoice is omitted from the destination list, so the user cannot simply return the payment there.
     3. The user is left guessing whether changing the destination to the same invoice should undo the reattribution.

5. Reattributed payments still expose ignore when they should require undo first
   - Surface: owner invoice show page, any actively reattributed payment row in payment history.
   - Repro:
     1. Reattribute a payment from a source invoice to a destination invoice.
     2. Open the correction UI for that actively reattributed row.
   - Expected:
     1. The row exposes the explicit undo path back to the source invoice.
     2. `Ignore` is unavailable while the payment is still actively reattributed.
     3. If the owner wants to ignore that payment later, they must first return it to the source invoice and then ignore it there.
   - Actual:
     1. The row still offers `Ignore` while the active accounting destination is another invoice.
     2. The UI allows overlapping correction directions instead of forcing the clean undo-first path.

## 2. Fix Sequence
### 1. Fix ignore validation recovery
1. [x] Keep the same ignore form open when validation fails for payment row `39` on invoice `67`.
2. [x] Preserve scroll position by returning focus to that payment row instead of reloading near the top.
3. [x] Focus the required reason field and keep the validation error inline and obvious.
4. [x] Make the failed submit state look clearly unsaved so the row does not read like the ignore succeeded.
5. [x] Add/update automated coverage for empty-reason ignore validation recovery.

### 2. Fix reattribution validation recovery
1. [x] Keep the same reattribution form open when validation fails for the `30,000 sats` / `$21.06` payment on invoice `69`.
2. [x] Preserve the selected destination invoice while returning the browser to that payment row.
3. [x] Focus the required reason field and keep the validation error inline and obvious.
4. [x] Make the failed submit state look clearly unsaved so the row does not read like the reattribution succeeded.
5. [x] Add/update automated coverage for empty-reason reattribution validation recovery.

### 3. Add manual adjustment reversal
1. [x] Add an owner-only reversal path that creates an equal-and-opposite manual adjustment row instead of editing or deleting the original row.
2. [x] Ship the inline two-step row UI: `Reverse` / `adjustment`, then `Confirm` / `reverse` / `entry`.
3. [x] Let a second click on `Reverse adjustment` hide the confirm control again.
4. [x] Auto-generate the reversal note as `reversal of {txid}` and recompute invoice state after the reversal entry is created.
5. [x] Add/update automated coverage for reversal creation and the append-only adjustment history.

### 4. Add reattribution undo
1. [x] Add a clear inline undo path for reattributed payments instead of forcing the user to infer that destination selection can act as reversal.
2. [x] Let the user return active accounting credit to the source invoice directly from the correction UI.
3. [x] Do not offer `Ignore` while a payment is actively reattributed; require the owner to undo first if they want to ignore that canonical payment row afterward.
4. [x] Keep the undo path explicit and stateful in both source and destination contexts so the current accounting destination is never ambiguous.
5. [x] Recompute source and destination invoice state immediately after undoing a reattribution.
6. [x] Add/update automated coverage for undoing a reattribution back to the source invoice and for blocking ignore while the reattribution is still active.

### 5. Verify the follow-up fixes
1. [x] Run the targeted automated coverage for ignore validation recovery, reattribution validation recovery, and manual adjustment reversal.
2. [x] Open invoice `67` / `INV-0003`, expand `Ignore` for payment row `39`, leave the reason empty, click `Confirm Ignore`, and verify the page stays anchored on that row, the ignore form stays open, the reason field is focused, the validation error is obvious, and the row does not read as ignored.
3. [ ] Open invoice `69` / `INV-0005`, start reattribution for the `30,000 sats` / `$21.06` payment detected `Mon, Mar 23, 2026 11:41 PM`, choose destination invoice `70` / `INV-0006`, leave the reason empty, submit, and verify the page stays anchored on that row, the destination selection is preserved, the reason field is focused, the validation error is obvious, and the row does not read as reattributed.
4. [x] Create a manual adjustment row on invoice `67`, `68`, `69`, or `70`, click `Reverse` / `adjustment`, verify `Confirm` / `reverse` / `entry` appears, click `Reverse` / `adjustment` again and verify the confirm control hides, then confirm a reversal and verify a new equal-and-opposite adjustment row appears with note `reversal of {txid}` while the original row stays in history. Also verify that once an entry has been reversed, it cannot be reversed again directly.
5. [ ] Reattribute a payment from source invoice `69` / `INV-0005` to destination invoice `70` / `INV-0006`, verify the actively reattributed row does not offer `Ignore`, then use the new undo path and verify the payment cleanly returns to the source invoice without guessing through destination selection, source/destination invoice math recomputes immediately, and the row no longer reads as reattributed.
