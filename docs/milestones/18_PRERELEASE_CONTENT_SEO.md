# MS18 - Pre-Release Content & SEO

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
- Active phase: **Phase 1 — Content strategy & Helpful Notes audit**
- Phase 1: _(strategy doc TBD)_
- Phase 2: _(strategy doc TBD)_
- Phase 3: _(strategy doc TBD)_
- Phase 4: _(strategy doc TBD)_

## Phase Rollup

### Phase 1 — Content strategy & Helpful Notes audit
Assess the existing Helpful Notes as raw material. For each note, decide: publish as-is, adapt/customize for a general audience, or use only as article inspiration. Produce a content plan — topics, formats, rough priority order — before any production work begins.

### Phase 2 — Site architecture
Extend `site/` to support multiple pages. Establish a staging/pre-publish workflow so content can be reviewed before the live site is updated.

### Phase 3 — Content production
Write and publish articles and any adapted Helpful Notes content per the Phase 1 plan. Video content produced here if time allows.

### Phase 4 — Publishing & SEO hygiene
Update `sitemap.xml` with all published URLs and accurate `lastmod` values. Verify internal linking. Run a final signal check against the MS15 baseline to confirm nothing regressed.

## Exit Criteria
- [ ] Content plan produced and approved (Phase 1 output).
- [ ] `site/` supports multiple pages with a working staging/pre-publish path.
- [ ] At least 2–4 articles or adapted Helpful Notes published on the live site, targeting stable educational queries.
- [ ] `sitemap.xml` reflects all published content with accurate `lastmod`.
- [ ] MS15 SEO baseline intact and extended — no regressions in indexing, canonical, robots, or sitemap signals.
- [ ] Video: shipped if time allowed; explicitly deferred and documented if not.
