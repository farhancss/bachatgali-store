# 0005 — Service-based domain architecture

**Status:** Accepted · **Date:** 2026-08-13

## Context

Laravel's defaults put logic in controllers and models. That is fine for a small application and
becomes unmanageable for a commerce platform where pricing, inventory, ordering and COD risk all
interact. The failure mode is well known: fat controllers, then a `Service` class per model that
grows to twenty methods and becomes a second controller.

## Decision

Organise `app/` into **Domain**, **Infrastructure** and **Http** layers, with dependencies
pointing inward only.

- **Domain** holds business logic grouped by bounded context (`Catalog`, `Pricing`, `Inventory`,
  `Ordering`, `Cod`, `Delivery`). It has no knowledge of HTTP, Inertia or any concrete gateway.
- **Infrastructure** holds implementations of external services, each behind an interface, each
  with a `Fake` used by the test suite.
- **Http** is thin: validate via FormRequest, call an Action, return a response.

Specific patterns:

- **Actions, not services.** One `final readonly class`, one public `handle()`. A class that does
  one thing cannot become a dumping ground.
- **DTOs at every boundary** (`spatie/laravel-data`). Arrays get mutated; typed objects do not.
- **Backed enums** for every status, with behaviour on the enum.
- **Explicit state machine** for orders. Illegal transitions throw rather than merely not happening.
- **Events for side effects.** Notifications, courier booking and reindexing live in listeners, so
  Actions stay stable and cheap to test.
- **Money is integer paisa**, wrapped in a value object. Floats never touch currency.
- **Repositories only where they earn it.** Eloquent is already a data layer; query objects cover
  complex reads.

## Consequences

- Business logic is unit-testable without a database or HTTP layer
- Swapping a courier is a new class and a config line
- More files and more indirection than idiomatic Laravel. This is a real cost, justified by the
  size and lifespan of the system — it would be over-engineering for a five-page brochure site
- The structure only survives if it is enforced. It is:
  [`tests/Arch/ArchitectureTest.php`](../../tests/Arch/ArchitectureTest.php) asserts the layering
  in CI. Discipline erodes by month four; a failing build does not.
