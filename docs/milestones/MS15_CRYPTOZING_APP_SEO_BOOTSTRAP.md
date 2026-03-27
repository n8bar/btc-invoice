# MS15 - CryptoZing.app SEO Bootstrap

Status: Draft as of 2026-03-26.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting docs: [`site/index.html`](../../site/index.html), [`site/sitemap.xml`](../../site/sitemap.xml), [`site/robots.txt`](../../site/robots.txt), [`docs/PRODUCT_SPEC.md`](../PRODUCT_SPEC.md)

This is the milestone execution doc for MS15. It tracks milestone-level objectives plus phase-level progress only.
SEO-oriented content work has already had a couple passes; MS15 should refine only what this milestone proves needs adjustment rather than reopening the placeholder for a broad rewrite.

## Milestone Objectives
- Get `cryptozing.app` discovered, crawled, and indexable before go-live.
- Verify the placeholder/landing page has the right sitemap, robots, canonical, and metadata baseline for search engines.
- Establish ownership, submission, and monitoring workflows for the domain in search-engine webmaster tooling.
- Preserve SEO continuity so the live app landing page can replace the placeholder at the same root URL without restarting discovery from zero.

## Current Focus
- Active phase: **Phase 1 - Discovery + Indexing Baseline**
- Current objective: review the Phase 1 strategy, then execute the repo-controlled discovery baseline work.
- Current phase strategy: [`docs/strategies/MS15_PHASE1_DISCOVERY_INDEXING_BASELINE.md`](../strategies/MS15_PHASE1_DISCOVERY_INDEXING_BASELINE.md)
- Primary surfaces: [`site/index.html`](../../site/index.html), [`site/sitemap.xml`](../../site/sitemap.xml), [`site/robots.txt`](../../site/robots.txt)

## Phase Rollup
1. [ ] Phase 1 - Discovery + Indexing Baseline
   Verify crawlability, sitemap/robots correctness, domain ownership, and initial search-engine submission for `cryptozing.app`.
2. [ ] Phase 2 - Metadata + Search-Signal Hygiene
   Tighten titles, descriptions, headings, canonical/schema signals, and only the minimum on-page copy needed to support the existing landing page.
3. [ ] Phase 3 - Continuity + Launch Handoff
   Define what must stay stable when the live app landing page replaces the placeholder at the same URL.
4. [ ] Phase 4 - Verification + Monitoring
   Capture indexing/discovery baselines, confirm the expected signals are live, and document what to monitor after launch.

## Exit Criteria
- `cryptozing.app` is verified in the intended webmaster/search-console tooling and its sitemap has been submitted.
- The placeholder/landing page is crawlable and exposes the intended robots, canonical, sitemap, and metadata signals.
- The placeholder exposes accurate, consistent search signals and does not make unsupported claims about the live product.
- The go-live handoff preserves URL/canonical continuity so the launch landing page does not reset discovery from scratch.
- A lightweight monitoring baseline exists for indexing status, sitemap health, and early search visibility after launch.
