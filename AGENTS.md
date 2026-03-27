# AGENTS

## Working Style
- Always run artisan/composer/npm commands through Sail (`./vendor/bin/sail ...`).
- Keep canonical docs in sync with every merge or scope change:
  - `docs/PLAN.md` for RC milestone order/status/current focus and the primary next doc
  - `docs/PRODUCT_SPEC.md` for global product behavior and invariants
  - `docs/BACKLOG.md` for post-MVP and deferred work only
- Keep the docs structure roles straight:
  - `docs/PLAN.md` lays out milestone-level progress only; each milestone should check off once there
  - if `docs/PLAN.md` has a `Next action`, keep it milestone-level too; do not pull phase-level or strategy-detail steps into it
  - `docs/milestones/**` expand a milestone into phase-level execution docs: objective/status summary, phase rollup, current focus, phase-level next actions, phase checkoffs, and milestone exit criteria
  - if a milestone doc has a current focus or next action, keep it phase-level; it may say to review the current phase strategy, but do not pull strategy-level checklist detail into the milestone doc
  - `docs/specs/**` for detailed feature and domain requirements
  - `docs/strategies/**` expand one milestone phase into the ordered implementation checklist, sequencing, and verification steps; these are the “do this in this order” docs for active execution
  - `docs/ops/**` for rollout, contributor, and deployment runbooks
  - `docs/qa/**` for findings, test plans, verification notes, and archive material
- Keep `docs/CHANGELOG.log` updated alongside canonical docs when scope or doc structure shifts.
- Maintain `docs/CHANGELOG.log` as plain text in chronological order (oldest first); append new entries at the bottom instead of prepending.
- Where dates are necessary in docs, use the date from the system you're running on.
- When adding features, update or create migrations + tests, then run `./vendor/bin/sail artisan test`.
- Also keep AGENTS.md updated to save on churn from session switching.
- Keep `.cybercreek/` local-only and untracked; do not commit agent coordination logs, local recovery files, or other local-only helper artifacts. For local-only work under `.cybercreek/`, follow `.cybercreek/AGENTS_LOCAL.md` if present.
- Keep the temporary GitHub Pages placeholder fenced under `site/`; treat it as a separate static surface from the Laravel app even though it lives in the same repo.
- Sail Compose includes a dedicated `scheduler` service that runs `php artisan schedule:work`; `./vendor/bin/sail up -d` keeps the watcher alive automatically.
- Specs come first: align on the requirement in the spec docs, implement, then update the docs to reflect what shipped; only reverse-engineer specs from existing code when we’ve explicitly agreed to do so.
- Docs are primarily internal architecture/engineering notes for us and future maintainers, not end-user documentation.
- Strategy docs (for example `docs/strategies/**`) own the ordered execution sequence for an active workstream: phased checklists, implementation order, and verification steps. They are authoritative for “what do we do next?” and resumption context, but they are not canonical for product scope or behavior; canonical requirements still live in `docs/PLAN.md`, `docs/PRODUCT_SPEC.md`, and the relevant docs under `docs/specs/**`. Strategy docs may or may not be retired, archived, or folded into milestone/history docs after completion.
- Keep checklist depth separated: `docs/PLAN.md` owns milestone checkoffs, milestone docs own phase checkoffs, and strategy docs own the ordered checklist for one phase. Higher-level docs should roll up lower-level completion with a single checkoff instead of duplicating lower-level checklist items.
- For any active workstream, keep one obvious checklist owner for sequencing. If a milestone doc and a strategy doc both exist, the milestone doc should summarize status/objectives while the strategy doc owns the detailed ordered checklist unless the docs explicitly say otherwise.
- Any doc with numbered tasks/milestones/todos is assumed to be done in order unless that doc explicitly says otherwise—flag any intentional deviations.
- If the user is asking for your input/feedback (e.g. “what do you think?”, “should we…?”, “does this make sense?”), answer first and confirm before making changes—even if the request sounds actionable.
- If asked to implement code before a spec exists, pause to confirm and recommend documenting the scope first (write the spec, then ship the code) unless the user explicitly insists otherwise.
- If you create a new doc/spec that shapes future implementation scope, pause for user review before treating that doc as approved implementation direction.
- If asked to merge a PR while there are uncommitted changes, unpushed commits, or any other local state that makes the tree non-clean or potentially misleading, pause and get explicit confirmation before merging.
- Before any push/PR, keep all docs in sync: update specs first when scope shifts, then code, and ensure everything under `docs/` (plus README links) reflects the same state in the same commit.
- Whenever `docs/**` or AGENTS.md changes, commit/push those updates right away. Exception: single-item checklist checkoffs in the same active workstream do not need to be pushed right away and may be committed together later.
- If the user has uncommitted doc edits in the same active workstream, preserve them and include them in the next related commit by default unless the user says otherwise.
- Apply the UX guardrails in [`docs/UX_GUARDRAILS.md`](docs/UX_GUARDRAILS.md) on every UX touch: Nielsen/WCAG as baseline; inline guidance, preserved input, no layout shift, focus/error handling, mobile/accessibility.
- GitHub `main` is canonical and protected. New work branches follow `codex/<task>`, and existing PRs must be updated via their original source branch rather than alternate branches.
- PRs are gated by GitHub Actions `PR Tests`; keep branches current with `origin/main` before requesting review.

## Multi-Agent Coordination
- Primary and secondary agents are role-based, not capability-limited: secondaries can work docs, code, tests, or modules within their stated task.
- Use subagents when the work can be split into independent, path-scoped tasks that materially reduce cycle time, especially for parallel code/doc/test updates or targeted read-only investigation.
- Keep the critical path with the primary agent: do not delegate the next blocking step just to use a subagent; the primary agent owns integration, final verification, and the user-facing summary.
- Assign each subagent a concrete deliverable plus clear file or module ownership; avoid overlapping write scopes, duplicated research, and broad "review the whole repo" style delegation.
- Prefer subagents for bounded sidecar work such as spec/doc sync, isolated test fixes, narrow codebase exploration, or risk review of a specific area while the primary agent continues non-overlapping work.
- Expect a dirty worktree during multi-agent sessions; do not stop for unrelated file changes outside your scoped paths.
- Pause only when unexpected changes appear in the same file you need to edit, or when a destructive/revert action would be required.
- Use path-scoped staging/commits (`git add <paths>`) so unrelated agent work is never swept into your commit.
- Keep agent coordination logs local-only and untracked. If you use coordination artifacts under `.cybercreek/` (for example `Agents.comm`), follow `.cybercreek/AGENTS_LOCAL.md`.
- On checkin, leave a short handoff note: what changed, what remains, and any risks/tests to run.

## Handy Commands
```
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
./vendor/bin/sail artisan wallet:assign-invoice-addresses --dry-run
./vendor/bin/sail artisan wallet:watch-payments
```

## Environment Notes (Do these without having to be reminded)
- Wallet xpub onboarding lives at `/wallet/settings`; invoices expect a configured wallet or redirect there.
- Node helper for BTC derivation lives in `node_scripts/derive-address.cjs` and is invoked via `App\Services\HdWallet`.
- **Data hygiene:** As of 2025-11-16 the app only holds seed/test data—no real customers yet. Remove this note (and treat production emails accordingly) once live customer data exists.
- CryptoZing must remain watch-only: never put private keys or seed phrases into tracked repo files, app config, database seeders, fixtures, tests, or normal application flows. If local testnet funding keys are needed for developer-only scenario setup, keep them only in untracked local storage (for example under `.cybercreek/`) and outside the product boundary.
- Email delivery currently rewrites recipients to the CryptoZing catch-all via `MAIL_ALIAS_ENABLED/MAIL_ALIAS_DOMAIN` (set to `mailer.cryptozing.app` so Mailgun routes everything to Proton). Disable the aliasing before RC or any real-customer deployment.
- `SUPPORT_AGENT_EMAILS` controls which accounts are treated as support accounts, and `SUPPORT_ACCESS_HOURS` should remain the fixed server-side expiration window for temporary owner-granted support access.
- The CryptoZing.app domain is reserved solely for this project; feel free to provision DNS/subdomains/mail for app needs without saving it for other products.
- Set `APP_PUBLIC_URL` to whatever domain should appear in public invoice links (localhost for dev, `https://cryptozing.app` for production) so emails never point at the wrong host.
- Keep the Sail stack (`./vendor/bin/sail up -d`) running during active work/testing unless there’s a clear reason to tear it down.
- Codex owns the terminal tooling: you drive Sail, git, and related commands—assume the user doesn’t have a shell open unless they say otherwise.
- For `.cybercreek/` changelog/findings handling, follow `.cybercreek/AGENTS_LOCAL.md`.
- Whenever `docs/**` changes, commit/push those updates right away. Exception: single-item checklist checkoffs in the same active workstream do not need to be pushed right away and may be committed together later.
- When you add or rename spec docs, update the README’s documentation section in the same commit so GitHub viewers always see the latest links.

## Roles
- **Harvey (Devil’s Advocate Progress Reporter):** virtual stakeholder who is skeptical but honest; invoked when we need a harsh readout. Focuses only on risk/gaps of “done” items, not future scope; calls out missing verification, operational proof, and doc drift. Keep tone blunt but actionable.
