# Testing Standards

_Established MS17 Phase 2. Apply to all new and modified tests from this point forward._

These rules exist to keep the suite fast, non-redundant, and meaningful going into RC. They are derived from the Pass 2 efficiency audit findings.

---

## 1. Use DatabaseTransactions by default

Use `DatabaseTransactions` unless a test relies on state persisting across multiple HTTP requests within the same test method. Only reach for `RefreshDatabase` when you genuinely need a clean slate between tests that share cross-request state.

```php
// Default
use Illuminate\Foundation\Testing\DatabaseTransactions;

// Only when tests share cross-request state within one test method
use Illuminate\Foundation\Testing\RefreshDatabase;
```

## 2. Use the shared invoice creation trait

Do not define a local `makeInvoice()` or equivalent helper in a test class. Use `Tests\Traits\CreatesTestInvoices` and extend it there if a new variant is needed.

```php
use Tests\Traits\CreatesTestInvoices;

class MyTest extends TestCase {
    use CreatesTestInvoices;
}
```

## 3. Assert behavioral outcomes, not implementation details

Tests should assert what the user or system observes — DB state, redirect target, session key, rendered text that carries meaning. They should not assert CSS classes, HTML attributes, log calls, or incidental copy that doesn't reflect product behavior.

```php
// Bad — implementation detail
$response->assertSee('overflow-x-auto');
$this->assertStringNotContainsString('autofocus', $html);

// Good — behavioral outcome
$this->assertDatabaseHas('invoices', ['status' => 'paid']);
$response->assertRedirect(route('invoices.show', $invoice));
```

## 4. One scenario per test, not one surface per test

A test should verify one logical scenario from one angle. Do not write one test that verifies the same state change across three view surfaces. Pick the most authoritative surface (usually the DB or the primary response) and assert there.

## 5. Unit tests must not hit the database

If a unit test requires `RefreshDatabase` or `DatabaseTransactions`, it is not a unit test — it is a feature test in the wrong directory. Move it or refactor it to test the logic in isolation.

## 6. Check before creating — search existing tests first

Before writing a new test, search the suite for an existing test that exercises the same setup (same model state, same route, same user context). If one exists and the new scenario is logically related, add an assertion to that test rather than creating a new one with its own DB setup and request cycle.

```bash
grep -r "invoices.show\|'status' => 'paid'" tests/
```

The cost of an extra assertion in an existing test is near zero. The cost of a new test is a full DB setup, model creation, and request cycle.

## 7. Augment existing tests before adding new ones

When a new scenario requires the same application state as an existing test, add it as an additional assertion in that test rather than a new test method. Prefer this when:

- The setup (user, invoice, payment state) would be identical or a subset
- The new assertion logically belongs in the same scenario
- Adding it does not make the existing test's intent ambiguous

Only spin up a new test when the scenario requires materially different setup, tests a meaningfully different path, or would make an existing test's name misleading.

---

## Quick checklist before submitting a new test

- [ ] Is there an existing test I could add an assertion to instead?
- [ ] Does the setup match an existing test closely enough to share it?
- [ ] Am I using `DatabaseTransactions` (or justifying `RefreshDatabase`)?
- [ ] Am I using `CreatesTestInvoices` instead of a local helper?
- [ ] Are my assertions on behavioral outcomes, not implementation details?
- [ ] If this is in `tests/Unit/`, does it touch the DB? (It shouldn't.)
