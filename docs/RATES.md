# Rate & Currency Handling

_Last updated: 2025-11-07_

## Source of Truth
- USD amounts entered by the user are canonical.
- BTC amounts are derived from USD ÷ current rate and recomputed whenever we render show/print/public views (unless rate data is unavailable, in which case we fall back to the stored BTC value).
- BIP21 URIs and QR codes reflect the BTC amount computed from the latest available rate so invoices always ask for enough sats at payment time; when payments knock the USD balance down, the QR recomputes using the outstanding USD only.

## Rounding Rules
- BTC values are rounded to **8 decimal places** (satoshi precision) using standard rounding.
- Display strings trim trailing zeros but never round beyond 8 decimals.
- USD values are formatted with 2 decimal places.

## Rate Cache
- `App\Services\BtcRate::CACHE_KEY` stores `['rate_usd','as_of','source']` with a TTL of 3600 seconds.
- Cached entries older than the TTL are discarded automatically and replaced with a fresh fetch on the next lookup.
- If both cache and fresh fetch fail, the UI hides the "Rate as of" block but continues to render using stored USD data.

## Refresh Flow
1. `BtcRate::current()` attempts to use the warm cache when it is inside the TTL window.
2. Stale or missing cache entries trigger `refreshCache()` (live fetch) transparently.
3. The Show page exposes a "Refresh rate" action that forces `refreshCache()` and recomputes the BTC/QR output (USD stays canonical).

## Test Expectations
- Feature tests assert 8-decimal BTC precision on the Show page, BIP21 links, and QR copy buttons.
- Rate tests cover cache hits, refresh behavior, and failure-mode rendering when live rates are unavailable.
