# MS18 - Pre-Release Content for SEO

Status: Active as of 2026-04-09.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)

**Relationship to MS15:** MS15 established the technical SEO foundation — domain verification, sitemap submission, canonical signals, robots, and controlled inbound links. That milestone asked "can Google find us?" This milestone asks "when someone searches, do we have an answer worth surfacing?" MS15 is a prerequisite; MS18 builds on it with substance.

## Milestone Objectives
- Extend `cryptozing.app` from a single placeholder page to a lightweight content site with multiple crawlable, indexed pages.
- Assess the existing Helpful Notes as raw material — publish, adapt/customize for a general audience, or use as inspiration for original articles depending on how product-agnostic they turn out to be.
- Produce educational articles targeting stable, query-matchable concepts: Bitcoin invoicing, USD-denominated invoices, on-chain payment confirmation, and related topics that prospective users are already searching.
- Establish a staging/pre-publish path so content can be reviewed before going live.
- Update `sitemap.xml` and `lastmod` as content ships.
- Consider video content if time allows; explicitly defer to post-RC if not — document the outcome either way.

## Current Focus
- Active phase: **Phase 1 — CMS Selection & Staging Setup**
- Phase 1: [`docs/strategies/18.1_CMS_AND_STAGING.md`](../strategies/18.1_CMS_AND_STAGING.md)
- Phase 2: [`docs/strategies/18.2_CONTENT_AUDIT_AND_PRODUCTION.md`](../strategies/18.2_CONTENT_AUDIT_AND_PRODUCTION.md)
- Phase 3: [`docs/strategies/18.3_SITE_ARCHITECTURE_AND_PUBLISHING.md`](../strategies/18.3_SITE_ARCHITECTURE_AND_PUBLISHING.md)

## Phase Rollup

### Phase 1 — CMS Selection & Staging Setup
Select a CMS and staging workflow as joint decisions — staging requirements may influence the CMS choice and vice versa. Set both up and confirm the full publish pipeline works end-to-end before any content is written.

### Phase 2 — Content Audit & Production
Audit the existing Helpful Notes, make Adapt/Inspire/Skip decisions, and produce all planned content. Move directly from audit decision to writing for each piece. Video at the end if time allows.

### Phase 3 — Site Architecture, Publishing & SEO Hygiene
Wire up the full multi-page structure, internal linking, and navigation around the Phase 2 content. Publish everything live. Update `sitemap.xml` and run a final signal check against the MS15 baseline.

## Exit Criteria
- [ ] CMS and staging workflow selected, set up, and verified end-to-end (Phase 1).
- [ ] Content plan approved before Phase 2 writing begins.
- [ ] At least 4 articles or adapted Helpful Notes published on the live site, targeting stable educational queries.
- [ ] `site/` supports multiple pages with a working staging path.
- [ ] `sitemap.xml` reflects all published content with accurate `lastmod`.
- [ ] MS15 SEO baseline intact and extended — no regressions in indexing, canonical, robots, or sitemap signals.
- [ ] Video: shipped if time allowed; explicitly deferred and documented if not.
