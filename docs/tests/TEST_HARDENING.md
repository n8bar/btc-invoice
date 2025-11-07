# Feature Test Draft – Test Hardening Initiative
_Last updated: 2025-11-07 (Codex)_

This document outlines the exact Feature tests we plan to implement next, reflecting the roadmap in `docs/PLAN.md`. Each test case lists preconditions, steps, and key assertions.

## 1. Public Share Lifecycle
### 1.1 Enable Public Share
- **Preconditions**: Invoice owned by acting user, `public_enabled = false`.
- **Steps**: PATCH `invoices/{invoice}/share/enable` via authenticated request (form submission).
- **Assertions**:
  - Response redirects with `status` flash `Public link enabled.`
  - Invoice refreshed has `public_enabled = true`, `public_url` present.
  - Eventual UI contains link + toggle state.

### 1.2 Disable Public Share
- Preconditions: Invoice with `public_enabled = true`.
- Steps: PATCH `.../share/disable`.
- Assertions: `public_enabled = false`, `public_token` unchanged, flash `Public link disabled.`

### 1.3 Rotate Public Share
- Preconditions: Invoice with sharing enabled.
- Steps: PATCH `.../share/rotate`.
- Assertions: `public_enabled` remains true, `public_token` changes, flash `Public link regenerated.` and `public_url` updated.

### 1.4 Expiry Handling
- Preconditions: Invoice with share enabled and expiry date.
- Steps: Fake time to post-expiry, visit `/p/{token}`.
- Assertions: View shows expired state message, `noindex` header persists, HTTP 410/403? (decide) but should not expose invoice data; route still returns friendly copy.

## 2. SEO / Meta – `noindex`
- Preconditions: Enabled public invoice.
- Steps: GET `/p/{token}` (HTML) and `invoices/print` if required.
- Assertions: `<meta name="robots" content="noindex, nofollow">` or equivalent header, plus `X-Robots-Tag` header equals `noindex, nofollow, noarchive`.

## 3. Show Page “Refresh Rate” Behavior
### 3.1 Rate Refresh Updates Copy & Timestamp
- Preconditions: Invoice with USD amount; stub `BtcRate::fresh` to return deterministic rate/time.
- Steps: Visit `invoices/{id}` (GET) to show initial data; trigger rate refresh endpoint (POST/patch) as UI does; reload Show page.
- Assertions:
  - Displayed BTC amount equals `amount_usd ÷ fresh_rate` rounded to 8 decimals.
  - “As of” text reflects new timestamp.
  - `AuthorizationTest` string still present when unauthorized.

### 3.2 Cache Reuse Across Requests
- Preconditions: Seed cached rate; stub BtcRate to track fetch count.
- Steps: Request Show twice without refresh.
- Assertions:
  - Rate service fetched only once; second view uses cache.
  - Both responses render identical `as_of` string until refresh occurs.

## 4. QR + BIP21 Accuracy
- Preconditions: Invoice with BTC amount computed.
- Steps: Visit Show and Print pages.
- Assertions:
  - DOM includes BIP21 link `bitcoin:{address}?amount={amount_btc}&label=...` with expected precision.
  - Copy button dataset matches link.
  - SVG `<path>` representing QR present (server-side generation) – check for `data:image/svg+xml` or `<svg id="invoice-qr">`.
  - Print view contains same URI and thank-you footer.

## 5. Soft Delete Visibility
### 5.1 Trash Listing
- Preconditions: Multiple clients/invoices, some soft-deleted, others active.
- Steps: DELETE resource, then GET `clients/trash` (and `invoices/trash`).
- Assertions: Soft-deleted records appear with proper ordering, active ones excluded.

### 5.2 Restore Flow
- Steps: PATCH `clients/{id}/restore`.
- Assertions: Record restored (no longer in trash, appears in index) and flash message returned.

### 5.3 Force Delete Authorization
- Preconditions: Another user tries to force delete.
- Steps: Auth as non-owner, call `clients/{id}/force`.
- Assertions: Response 403 + friendly copy (reuses Authorization test helper).

## 6. Rate Cache Reuse / Fallbacks
- Preconditions: Mock rate cache service.
- Steps: Simulate failure of live fetch; rely on cached rate.
- Assertions: Show page still renders values, displays fallback messaging (if any) yet test ensures no exception.

## Implementation Notes
- Use `RefreshDatabase` + factories; prefer `actingAs` to simulate owners/non-owners.
- For rate mocking, bind `App\Services\BtcRate` fake via `app()->bind` within test or use `swap`.
- Use `Carbon::setTestNow` for timing scenarios.

