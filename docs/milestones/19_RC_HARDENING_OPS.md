# MS19 - RC Hardening & Ops

> **Stub** — high-level scope and decisions recorded. Phase strategy docs and detailed exit criteria to be written when this milestone becomes active.

Status: Not started.
Parent execution doc: [`docs/PLAN.md`](../PLAN.md)
Supporting ops doc: [`docs/ops/DOCS_DX.md`](../ops/DOCS_DX.md)

## Milestone Objectives
- Document notification coverage so the full outbound mail surface is explicitly accounted for before RC.
- Add auth and password policy hardening: 419-to-login redirect, site-wide session expiry logout.
- Keep contributor docs current.
- Implement any UI code changes deferred from MS20 legal scoping (disclaimer placement, footer ToS/Privacy Policy links).

## Phases
_(Phase strategy docs to be written when this milestone becomes active.)_

- Phase 1 — Notification coverage documentation
- Phase 2 — Auth/password policy hardening
- Phase 3 — Legal disclaimer UI implementation (deferred from MS20 scoping)
- Phase 4 — Contributor docs review and update

## Exit Criteria
_(To be detailed when active.)_

- [ ] Notification coverage documented: every outbound mail type accounted for with intended trigger, recipient, and delivery log behavior.
- [ ] 419-to-login redirect implemented and tested.
- [ ] Site-wide session expiry logout implemented and tested.
- [ ] Disclaimer copy present at signup, wallet onboarding, and invoice/payment surfaces; footer links to ToS and Privacy Policy on every page.
- [ ] Contributor docs reviewed and current.
