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
