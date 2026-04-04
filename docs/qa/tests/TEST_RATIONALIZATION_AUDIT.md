# Test Suite Rationalization Audit

_Produced for MS17 Phase 2. See [`docs/strategies/17.2_TEST_RATIONALIZATION.md`](../../strategies/17.2_TEST_RATIONALIZATION.md) for process._

Status: **Pass 2 complete. Awaiting review before execution.**

---

## Audit Table

| File | What it covers | Relevance | Correctness | Recommendation | Notes |
|---|---|---|---|---|---|
| `Feature/Auth/AuthenticationTest.php` | Login render, password toggle, post-login routing by user state (incomplete → getting-started, wallet → getting-started, completed → dashboard, replay → getting-started), invalid credentials, support routing | High | High | Keep | Tests critical redirect paths for all onboarding states. |
| `Feature/Auth/EmailVerificationTest.php` | Verification screen render, valid hash success, invalid hash rejection | High | High | Keep | Covers signed URL mechanics correctly. |
| `Feature/Auth/PasswordConfirmationTest.php` | Confirm screen render, success, wrong password rejection | High | High | Keep | Minimal but correct. |
| `Feature/Auth/PasswordResetTest.php` | Reset link screen, link dispatch, reset screen, password reset with valid token | High | High | Keep | Verifies token validity and redirect behavior cleanly. |
| `Feature/Auth/PasswordUpdateTest.php` | Password update with correct/wrong current password | High | High | Keep | Validates hash verification and error responses. |
| `Feature/Auth/RegistrationTest.php` | Registration render, password toggles, new user registration and redirect, mixed-case email preservation | High | High | Keep | Email case-preservation is important for lookups; well-tested. |
| `Feature/ExampleTest.php` | Homepage returns 200 and contains a help link | Low | Low | Delete | Default scaffold test. No app-specific signal. |
| `Unit/ExampleTest.php` | `assertTrue(true)` | Low | Low | Delete | Placeholder. No coverage at all. |
| `Feature/AuthorizationTest.php` | Non-owner 403 on client and invoice views | High | High | Keep | Policy enforcement — essential for security audit. |
| `Feature/ClientShowRedirectTest.php` | Client show redirect, index list and action bar, update flash, delete section, email required at form and schema level | High | High | Keep | Comprehensive client CRUD. Email validated at multiple layers. |
| `Feature/Console/BackfillInvoicePaymentsCommandTest.php` | Backfill creates missing payment row, skips invoices with existing payments | High | High | Keep | Data-integrity migration logic with edge-case handling. |
| `Feature/Console/ScheduleTest.php` | `wallet:watch-payments` scheduled every minute with overlap prevention and background flag | High | High | Keep | Verifies critical scheduler config for payment watching. |
| `Feature/DashboardSnapshotTest.php` | Snapshot display, payment aggregation, soft-delete respect, cache isolation, TTL, ignored/reattributed payment exclusion, receipt review action items, getting-started and account menu state | High | High | Keep | Exhaustive dashboard correctness coverage. |
| `Feature/GettingStartedFlowTest.php` | All step transitions, skip-ahead blocking, replay mode, dismiss/reopen, draft sync before completion, invoice selection logic, wallet guidance | High | High | Keep | Thorough state-machine tests. Critical for onboarding correctness. |
| `Feature/HelpfulNotesTest.php` | Help page public/indexable, back navigation, wallet settings anchor link, dedicated account guidance | High | High | Keep | SEO flags and back-link correctness. |
| `Feature/InvoiceDeliveryTest.php` | Delivery queueing, draft message save | Medium | Medium | Tweak | Only first ~100 lines read during audit. Verify full file covers all delivery types; adjust any assertions that have drifted from current type names. |
| `Feature/InvoiceNotificationTest.php` | Issuer paid notice, overpay/underpay alerts, past-due scheduling (slots 1 and 2), alert suppression on existing failures | High | High | Keep | Comprehensive notification lifecycle coverage. |
| `Feature/InvoicePaymentCorrectionTest.php` | Ignore/restore flow, print visibility, audit logging, delivery skipping on ignore | Medium | Medium | Tweak | Only first ~150 lines read. Verify full file; adjust any drifted assertions. |
| `Feature/InvoicePaymentDisplayTest.php` | BIP21/QR controls, payment note visibility, print view customization, billing overrides | Medium | Medium | Tweak | Only first ~150 lines read. Verify full file; check watermark and print assertions. |
| `Feature/InvoiceRateTest.php` | Cached rate display, refresh, staleness detection, graceful API failure | High | High | Keep | Rate caching and degradation logic well-tested. |
| `Feature/InvoiceShowEditFlowTest.php` | Index empty state, ID column preference, current-rate BTC display, unsupported state visibility | Medium | Medium | Tweak | Only first ~150 lines read. Verify full file for show/edit surface coverage. |
| `Feature/MailBrandingTest.php` | CryptoZing shared branding, owner override branding, test email preview render | High | High | Keep | Branding customization and logo inline/hidden logic. |
| `Feature/MailgunWebhookTest.php` | HMAC signature validation, delivered/failed/bounced event handling, unknown message ID no-op | High | High | Keep | Critical for delivery status tracking; good signature tests. |
| `Feature/ProfileTest.php` | Profile display, password toggles, info update, settings redirect, email-unchanged detection, account deletion | High | High | Keep | Covers account lifecycle and preference persistence. |
| `Feature/PublicShareTest.php` | Enable/disable/rotate share, public URL config, SEO noindex, expired state, branding display, owner-only toggle | High | High | Keep | Comprehensive public link coverage including expiry and SEO. |
| `Feature/SupportAccessTest.php` | Grant/revoke, profile copy, dashboard issuer list, read-only invoice/client views, write prevention | High | High | Keep | Security-critical: ownership gates and write prevention well-tested. |
| `Feature/ThemePreferenceTest.php` | Theme update (dark/light/system), invalid theme rejection | Medium | Medium | Keep | Simple but correct; important for UX persistence. |
| `Feature/TrashFlowsTest.php` | Trash/restore for client and invoice, force-delete ownership gate, blocking on ignored payments and reattributions, schema backstop | High | High | Keep | Data integrity under deletion — multiple blocking scenarios covered. |
| `Feature/UserSettingsTest.php` | Invoice defaults applied on create, draft enforcement, unsupported wallet flagging, prefill | High | High | Tweak | Only first ~200 lines read. Verify full file; likely covers wallet settings and notification preferences. |
| `Feature/Wallet/InvoiceAddressCommandsTest.php` | Cursor ledger on assign, lineage on reassign, index advancement | High | High | Keep | HD wallet derivation and ledger tracking. |
| `Feature/Wallet/WatchPaymentsCommandTest.php` | Testnet4 and testnet3 API base URLs, invoice lineage used when current wallet differs | High | High | Tweak | Only first ~150 lines read. Verify full file covers confirmation logic, API error handling, and payment recording. |
| `Unit/HdWalletTest.php` | Testnet/mainnet vpub/zpub derivation at indices 0 and 1, network alias acceptance, mismatch rejection | High | High | Keep | Deterministic derivation with known vectors — essential crypto unit tests. |
| `Unit/InvoicePaymentSummaryTest.php` | Outstanding sats clamped to zero, ignored payment exclusion, reattribution destination-only counting | High | High | Keep | Core accounting logic for invoice state calculation. |
| `Unit/MailAliasTest.php` | Alias conversion when enabled/disabled, whitespace normalization, invalid email passthrough | High | High | Keep | Utility correctness; all cases covered. |

---

## Recommended deletions

- `Feature/ExampleTest.php` — no signal, replace with nothing
- `Unit/ExampleTest.php` — placeholder, delete

## Files flagged for Tweak

These files were partially read during the audit (file sizes exceeded the read window). Before executing any changes, read the full file to confirm the recommendation and scope the adjustment.

- `Feature/InvoiceDeliveryTest.php` — verify delivery type strings match current issuer_* names after Phase 1
- `Feature/InvoicePaymentCorrectionTest.php` — verify no drifted assertions
- `Feature/InvoicePaymentDisplayTest.php` — verify watermark, print, and billing override assertions
- `Feature/InvoiceShowEditFlowTest.php` — verify full show/edit surface coverage
- `Feature/UserSettingsTest.php` — verify wallet settings and notification preference tests
- `Feature/Wallet/WatchPaymentsCommandTest.php` — verify confirmation logic and error handling coverage

---

## Coverage gaps (RC-critical)

Surfaces with zero or near-zero test coverage that matter for RC:

1. **Blockchain confirmation logic** — No explicit tests for block height tracking, confirmation counting, mempool API error handling/retries, or double-spend detection. `WatchPaymentsCommandTest` is partially read; this may be partially covered.
2. **Receipt content** — Tests verify delivery queueing but not receipt email content, layout, or truthfulness invariants.
3. **Invoice state machine** — No dedicated state-transition tests. Transitions are implicitly exercised but `draft→sent→partial→paid` paths and their blocking conditions are not explicitly asserted.
4. **Multi-wallet / wallet reconnect** — All tests assume a single wallet per user. No coverage for wallet reconnection, legacy key retirement, or address reuse detection across wallets.
5. **Dashboard cache invalidation on payment events** — TTL behavior tested; event-driven invalidation is not.

Gaps 3–5 are deferred to the backlog unless the review in Section 2 surfaces a specific RC risk. Gap 1 should be confirmed once `WatchPaymentsCommandTest` is fully read. Gap 2 is partially mitigated by `MailBrandingTest` but receipt truthfulness invariants (no receipt on unresolved correction state) are untested.

---

## Efficiency Recommendations

_Pass 2 — produced after full reads of all Tweak files and cross-file redundancy scan._

### Critical: Unit test using RefreshDatabase

`Unit/InvoicePaymentSummaryTest` uses `RefreshDatabase` and took **16.83s** in timing — nearly the entire rest of the suite. It creates real DB rows to test in-memory math that could be tested with model stubs or `DatabaseTransactions`. This is the single biggest win in the suite.

**Action:** Switch to `DatabaseTransactions` or replace DB-backed setup with direct model instantiation where the math doesn't require persisted relationships.

---

### RefreshDatabase → DatabaseTransactions

Switch these files from `RefreshDatabase` to `DatabaseTransactions`. Each file's tests are isolated single requests with no cross-request shared state.

| File | Tests | Estimated savings |
|---|---|---|
| `Feature/InvoiceDeliveryTest.php` | 20 | ~0.3s |
| `Feature/InvoicePaymentCorrectionTest.php` | 17 | ~0.25s |
| `Feature/InvoicePaymentDisplayTest.php` | 27 | ~0.4s |
| `Feature/Wallet/WatchPaymentsCommandTest.php` | 17 | ~0.25s |

Keep `RefreshDatabase` in `InvoiceShowEditFlowTest` (multi-step request sequences) and `UserSettingsTest` (heavy config mutation).

---

### Shared invoice creation trait

Five files define their own `makeInvoice()` helper with incompatible signatures. Consolidate into `Tests/Traits/CreatesTestInvoices.php`:

```php
trait CreatesTestInvoices {
    protected function makeInvoice(?User $owner = null, array $overrides = []): Invoice { ... }
    protected function makeInvoiceWithNetwork(string $network, ?User $owner = null): Invoice { ... }
}
```

Affected files: `InvoicePaymentCorrectionTest`, `InvoicePaymentDisplayTest`, `DashboardSnapshotTest`, `GettingStartedFlowTest`, `WatchPaymentsCommandTest`.

---

### Tests to delete (no behavioral value)

1. `InvoicePaymentCorrectionTest::test_ignore_validation_page_does_not_render_native_autofocus_on_teleported_correction_fields` — asserts HTML attribute absence, not behavior.
2. `InvoicePaymentCorrectionTest::test_reattribution_validation_page_does_not_render_native_autofocus_on_teleported_correction_fields` — same issue, mirror test.

---

### Cross-file redundancy to collapse

| Scenario | Files | Action |
|---|---|---|
| Owner paid notice queued on payment | `InvoiceDeliveryTest`, `WatchPaymentsCommandTest`, `InvoiceNotificationTest` | Keep Delivery + WatchPayments (different triggers); remove `InvoiceNotificationTest` version unless it uniquely tests event dispatch |
| Unsupported wallet flag | `UserSettingsTest` (state persistence), `InvoiceShowEditFlowTest` (view rendering) | Keep both — different concerns. Clarify test names. |

---

### Over-asserting: specific fixes

| Test | Problem | Fix |
|---|---|---|
| `InvoiceShowEditFlowTest::test_invoice_index_empty_state_*` | Asserts CSS class `overflow-x-auto` | Remove CSS assertion |
| `InvoiceDeliveryTest::test_owner_can_queue_invoice_email` | Asserts all delivery row fields including cc/message | Reduce to type, status, recipient |
| `InvoicePaymentDisplayTest::test_show_displays_bip21_link_*` | Asserts `Thank&nbsp;you!` incidental copy | Remove copy assertion |
| `InvoicePaymentDisplayTest` overpay/underpay/tolerance tests | Three separate tests for threshold variants | Consolidate with `@dataProvider` |

---

### Under-asserting: specific fixes

| Test | Problem | Fix |
|---|---|---|
| `InvoiceShowEditFlowTest::test_invoice_update_redirects_*` | Asserts flash only; doesn't verify updated data renders | Chain GET to show page, assert updated values |
| `InvoicePaymentDisplayTest::test_print_view_uses_billing_overrides_*` | Asserts custom values appear; doesn't verify defaults gone | Add `assertDontSee` for default values |
| `UserSettingsTest::test_user_can_add_and_remove_additional_wallet_accounts` | Asserts redirect only | Follow with GET to settings, verify account listed |
| `WatchPaymentsCommandTest::test_command_marks_invoice_partial_when_underpaid` | Asserts status='partial' only | Add assertion for `payment_amount_sat` |
