# MS20 - Mainnet Cutover Preparation

Status: Not started.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting ops doc: [`docs/ops/RC_ROLLOUT_CHECKLIST.md`](../ops/RC_ROLLOUT_CHECKLIST.md)

## Milestone Objectives
- Define and rehearse the env flips, wallet validation, mail sanity checks, and backout steps needed for a safe mainnet cutover.
- Put a minimum legal layer in place before go-live: Terms of Service, Privacy Policy, disclaimer copy at key user touchpoints, and a monetization-neutral language pass across existing UI and mail copy.

## Decisions recorded
- **Legal approach:** No lawyer for RC1. Self-drafted ToS and Privacy Policy covering the essential bases — not financial advice, no custody of funds, user responsibility for keys, no warranty on BTC/USD values.
- **Disclaimer surfaces:** Account signup, wallet onboarding, and invoice/payment screens are the three required touchpoints. Footer links to ToS and Privacy Policy on every page.
- **Monetization-neutral language:** Avoid language that permanently forecloses pricing options ("always free," "no fees ever"). Leave room for future paid tiers or feature gating without requiring a ToS rewrite.
- **Implementation split:** Legal doc drafting and copy review happen in this milestone. Any UI code changes (disclaimer placement, footer links) land in MS19 RC Hardening so they are tested before cutover.

## Phases
_(Phase strategy docs to be written when this milestone becomes active.)_

- Phase 1 — Cutover rehearsal: env flips, wallet validation, mail sanity, backout steps
- Phase 2 — Legal layer: ToS draft, Privacy Policy draft, disclaimer placement checklist, copy review pass

## Exit Criteria
_(To be detailed when active.)_

- [ ] Cutover runbook complete and rehearsed.
- [ ] ToS and Privacy Policy drafted and published to the live site.
- [ ] Disclaimer copy present at signup, wallet onboarding, and invoice/payment surfaces.
- [ ] Existing UI and mail copy reviewed for overstatements, financial advice language, and pricing commitments — issues resolved.
- [ ] Monetization-safe language guide produced for future copy decisions.
