# Project Plan

> Maintained by Codex – this document is updated whenever PRs land or the delivery plan changes.

## Overview
- Build and maintain a Laravel-based Bitcoin invoicing SaaS MVP that enforces resource ownership, surfaces friendly 403 pages, and supports invoice lifecycle workflows.
- Keep the codebase deploy-ready by ensuring CI (GitHub Actions `phpunit`) stays green and Sail-based local validation mirrors CI.
- Document run rules, branching expectations, and roadmap milestones so contributors can move quickly without breaking guardrails.

## Stack & Environment
- **Framework**: Laravel 11 (PHP 8.2) with Breeze scaffolding.
- **Runtime**: Laravel Sail (Docker) containers: `laravel.test` (PHP-FPM + Nginx) and `mysql`.
- **Databases**: MySQL 8 via Sail; SQLite only used implicitly in some tests but default is MySQL.
- **Queues/Jobs**: Not currently provisioned; sync driver.
- **Front-end**: Blade templates with Tailwind CSS, Vite, and Alpine (from Breeze baseline).
- **Tooling**: Composer, npm, PHPUnit, Pest (not currently), Laravel Pint optional.

## Run Rules (Sail Only)
- All commands that interact with PHP or the database **must** run through Sail to avoid host/tool mismatch.
- Bring the stack up before running anything: `./vendor/bin/sail up -d`.
- Testing: `./vendor/bin/sail artisan test` (avoid `php artisan`).
- Artisan commands follow the same rule (`./vendor/bin/sail artisan migrate`).
- Containers can remain running between commands to minimize startup cost; shut down with `./vendor/bin/sail down` when finished.

## Source of Truth & Branching Policy
- `main` is the canonical source. Only fast-forward merges from vetted PRs land here.
- Active work happens on `codex/*` topic branches (e.g., `codex/implement-ownership-policies-with-friendly-403`, `codex/ci-smoke`). Reuse existing PR branches when revising a PR; do **not** create parallel branches for the same ticket.
- When a GitHub PR already exists (e.g., PR #13), always push fixes to that PR’s source branch.
- Short-lived experiment branches should still follow the `codex/*` prefix.
- Keep branches rebased/merged with `origin/main` before requesting review to avoid stale conflicts.

## CI Gate
- GitHub Actions workflow `phpunit` runs on every push and PR. It must remain green before merges.
- CI runs `composer install`, `npm ci` (if needed), Sail/Laravel test suite, and enforces code style implicitly via tests.
- Never merge when CI is pending or red; re-run locally via Sail, fix, then push.

## Testing Approach
- Primary suite: `./vendor/bin/sail artisan test` (PHPUnit) covering Feature + Unit tests.
- Prefer feature tests for HTTP/policy flows (see `tests/Feature/AuthorizationTest.php`).
- Add regression tests alongside bug fixes; keep them deterministic (seeded with factories, `RefreshDatabase`).
- For local smoke checks without full suite, run targeted files (e.g., `./vendor/bin/sail artisan test tests/Feature/AuthorizationTest.php`).
- Front-end logic currently server-rendered; no JS test harness required yet.

## Time Handling
- Store and compare timestamps in UTC (Laravel default). Use `now()`/`Carbon::now()` which respect app timezone; prefer `now()->toDateString()` in tests.
- Display dates using localized helpers in Blade when exposed to users.
- When seeding tests, pin explicit `now()` outputs to avoid drift.

## Key Paths
- `app/Http/Controllers` – HTTP layer, policy enforcement via `authorizeResource`.
- `app/Policies` – ownership rules for `Client` and `Invoice` models.
- `app/Providers` – `AppServiceProvider` (app bootstrapping) & `AuthServiceProvider` (policy map).
- `bootstrap/app.php` – application bootstrap plus exception rendering (shared 403 template).
- `resources/views/errors/403.blade.php` – shared forbidden UI copy.
- `tests/Feature/AuthorizationTest.php` – canonical assertions for 403 messaging.
- `docs/PLAN.md` – this plan (single source for cadence & roadmap).

## Current Status (2025-11-07)
- Ownership policies enforced on `Client`/`Invoice` controllers via `authorizeResource`.
- Friendly 403 page wired through `bootstrap/app.php`, matching test expectations.
- CI is healthy (latest `phpunit` runs passing) and Sail workflow validated.
- No open functional regressions identified; next focus is planning cloud deployment readiness.

## Roadmap to Release Candidate (Cloud Deployable)
1. **Infrastructure Prep (Week 1)**
   - Define target cloud (e.g., AWS ECS/Fargate or DigitalOcean Apps) and required secrets (DB creds, APP_KEY, queue driver).
   - Add IaC placeholders or deployment scripts (Terraform or Docker Compose override) to repo.
2. **Environment Hardening (Week 2)**
   - Introduce config for production mail/payout integrations if applicable.
   - Add health-check route coverage and logging tweaks.
3. **Data Integrity & Backups (Week 3)**
   - Implement scheduled DB backups or document managed backup strategy.
   - Verify migrations cover upgrades from current schema.
4. **Observability & Alerts (Week 4)**
   - Wire basic error reporting (Sentry/Bugsnag) & uptime monitoring.
   - Add log channel config suitable for cloud.
5. **Security & Access (Week 5)**
   - Enforce HTTPS, secure headers, review auth flows (password reset, 2FA optional).
6. **Performance & Load Smoke (Week 6)**
   - Run Sail-based load test or deploy-preview soak test; tune DB indexes if needed.
7. **Release Candidate Cut (Week 7)**
   - Tag `v1.0.0-rc1`, deploy to staging cloud environment, run migration & sanity suite.
   - Update README/deployment docs with final steps.

## Changelog / Decisions Log
| Date (UTC) | Change | Notes |
|------------|--------|-------|
| 2025-11-07 | Added docs/PLAN.md and README link | Establishes Codex-maintained project plan and CI/update policy. |
| 2025-11-07 | CI smoke workflow verified | `codex/ci-smoke` merged after phpunit success; establishes baseline for future CI triggers. |

