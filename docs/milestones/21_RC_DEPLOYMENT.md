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

## Deferred decisions (recorded during MS18 Phase 1)
- **Content site architecture:** Static content files served by nginx directly alongside the Laravel app — path-based split, same domain, no PHP involved for content routes. CMS selection (Jekyll for GitHub Pages) may be revisited at this point to evaluate whether migrating to Hugo or another tool better fits the production nginx setup. Jekyll output is portable so migration is not a rework.
- **Staging area transition:** The dev server staging area built during MS18 is intended to become the production content site at cutover — not throwaway infrastructure. Copy staging content directly to production; no rebuild or re-architecture expected.
- **Post-RC staging:** Define a new staging workflow at this milestone — options include a new dev path or a password-protected area on production.
- **URL structure:** Content paths (e.g. `/articles/`) to be confirmed or adjusted based on CMS selection and nginx config at cutover.

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
