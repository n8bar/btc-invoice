# MS21 - CryptoZing.app Deployment (RC)

> **Stub** — high-level scope and decisions recorded. Phase strategy docs and detailed exit criteria to be written when this milestone becomes active.

Status: Not started.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting ops doc: [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](../ops/RC_ROLLOUT_CHECKLIST.md)

## Milestone Objectives
- Deploy the RC under `cryptozing.app`.
- Replace the GitHub Pages placeholder at `/` with the live app landing page without breaking the SEO baseline established in MS15 and extended in MS18.
- Remove temporary mail aliasing.
- Complete rollout verification per the RC rollout checklist.

## Decisions recorded (during MS18 Phase 1)
- **Content site architecture:** Static content files served by nginx directly alongside the Laravel app — same domain, no PHP involved for content routes. CMS is Eleventy (selected in MS18 Phase 1); evaluate at RC deployment whether to keep Eleventy or migrate — static output means migration is never a rework.
- **URL structure:** Content lives at `cryptozing.app/learn/*`. Articles authored in `site/learn/`, Eleventy outputs to `public/content/learn/`. GitHub Pages serves these URLs pre-RC; DNS cutover at MS21 points `cryptozing.app` at nginx, which serves the same paths unchanged. SEO value built pre-RC is fully preserved — URLs never move.
- **Cutover mechanics:** Only `cryptozing.app/` changes at cutover — the placeholder is replaced by the Laravel landing page. Everything under `/learn/` continues serving from the same URLs, just via nginx instead of GitHub Pages.
- **Staging:** Dev server (`public/content/` via Sail) is the staging environment during MS18–MS20. At RC deployment, the built `public/content/` output is what nginx serves. Post-RC staging options to be decided post-RC.
- **GitHub Pages retirement:** GitHub Pages is retired at DNS cutover — not deleted, just no longer the DNS target. No redirects needed; URLs are preserved by the nginx serving the same paths.
- **GitHub nav link:** Remove the GitHub link from the site nav before RC deployment — it's pre-release framing. Keep the footer link as-is; consider updating copy post-RC if it no longer fits.

## Phases
_(Phase strategy docs to be written when this milestone becomes active.)_

- Phase 1 — Pre-deploy verification: env, wallet, mail, DNS, SEO baseline check
- Phase 2 — Deploy and cutover
- Phase 3 — Post-deploy verification and rollout sign-off

## Exit Criteria
_(To be detailed when active.)_

- [ ] RC deployed and reachable under `cryptozing.app`.
- [ ] Live app landing page replaces GitHub Pages placeholder at `/`; SEO baseline intact (canonical, sitemap, robots, indexed URLs).
- [ ] Temporary mail aliasing removed; outbound mail routes through production config.
- [ ] All RC rollout checklist items completed and signed off.
