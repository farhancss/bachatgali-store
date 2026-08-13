# Bachat Gali — Store

Cash-on-delivery commerce platform for the Pakistani market. Replaces the previous WooCommerce site.

**Laravel 13 · Inertia v2 · Vue 3 · Filament 4 · PostgreSQL 17 · Typesense**

> **Status: boilerplate.** Phase 0 scaffold — architecture, tooling, quality gates and one worked
> vertical slice. No business features are implemented yet. See [the roadmap](docs/01-architecture.md#12-delivery-roadmap).

---

## What this is

A COD-only storefront where the hard problems are not rendering products — they are **RTO
(return-to-origin) losses, courier reconciliation and cash in transit**. The architecture is
organised around that reality: `app/Domain/Cod` is a first-class bounded context, not a payment
adapter.

Three decisions shape everything else:

1. **Cash on delivery is the only payment method.** No gateway, no PCI scope, no card data anywhere.
   In exchange, the platform carries the financial risk — so risk scoring, OTP verification and
   remittance reconciliation are core features, not afterthoughts.
2. **Rendering is hybrid.** SEO-critical catalog pages are server-rendered Blade and cached at the
   edge. Cart, checkout and account are Inertia. See [ADR-0003](docs/adr/0003-hybrid-blade-inertia-rendering.md).
3. **The domain layer knows nothing about the framework.** Dependencies point inward only, and
   [architecture tests](tests/Arch/ArchitectureTest.php) enforce it in CI rather than relying on
   code-review discipline.

---

## Quick start

```bash
git clone git@github.com:farhancss/bachatgali-store.git
cd bachatgali-store

cp .env.example .env

# Everything: PHP 8.4 + Octane, PostgreSQL 17, Redis, Typesense, Mailpit
docker compose up -d

composer install
npm install

php artisan key:generate
php artisan migrate

npm run dev
```

Storefront on <http://localhost:8000>, mail catcher on <http://localhost:8025>.

No courier credentials are needed to run locally — `COURIER_DEFAULT=fake` uses an in-memory
gateway. Full setup notes in [docs/05-getting-started.md](docs/05-getting-started.md).

---

## Before you push

```bash
composer check
```

Runs the complete gate suite: Pint, PHPStan level 8, PHPStan level 9 on the domain, Rector
dry-run, architecture tests, test suite with coverage, and type coverage. If this is green
locally, CI will be green.

| Command | Does |
|---|---|
| `composer test` | Full suite, parallel |
| `composer test:arch` | Architecture rules only (~5s) |
| `composer test:cov` | Coverage, fails under 85% |
| `composer test:type` | Type coverage, fails under 95% |
| `composer test:mutate` | Mutation score on `Pricing` + `Cod`, fails under 75% MSI |
| `composer types` | PHPStan level 8 |
| `composer types:domain` | PHPStan level 9 on `app/Domain` |
| `composer fix` | Apply Pint formatting |
| `composer refactor` | Rector dry-run |
| `npm run types` | `vue-tsc` strict |

---

## Layout

```
app/
├── Domain/                  business logic — no framework dependencies
│   ├── Shared/              Money, City, shared casts and contracts
│   ├── Catalog/             products, variants, categories, brands
│   ├── Pricing/             price calculation, vouchers, discount rules
│   ├── Inventory/           stock ledger, reservations
│   ├── Ordering/            orders, order state machine
│   ├── Cod/                 ← risk scoring, OTP, remittance reconciliation
│   └── Delivery/            shipments, tracking, rate calculation
├── Infrastructure/          the outside world, behind interfaces
│   ├── Courier/             PostEx · Leopards · TCS · Fake
│   └── Sms/
├── Http/                    thin controllers, form requests, middleware
├── Filament/                admin panel resources and widgets
└── Providers/               contract → implementation bindings
```

**The rule:** `Domain` may not reference `Http`, `Inertia` or a concrete gateway. Controllers may
not touch Eloquent directly. Money is always an integer of paisa, never a float. All three are
asserted by tests that run in five seconds.

---

## Documentation

| Doc | Covers |
|---|---|
| [01 — Architecture & build plan](docs/01-architecture.md) | Full system design, feature scope, roadmap, costs, risks |
| [02 — Architecture reference](docs/02-architecture-reference.md) | Real code for the service layer, DTOs, state machine, tests |
| [03 — COD operations](docs/03-cod-operations.md) | Risk scoring, OTP, RTO control, remittance reconciliation |
| [04 — Testing & quality gates](docs/04-testing-and-quality.md) | Test pyramid, arch tests, static analysis, CI pipeline |
| [05 — Getting started](docs/05-getting-started.md) | Local setup, conventions, how to add a feature |
| [ADRs](docs/adr/) | Why Laravel, why hybrid rendering, why COD-only |
| [Prototype](docs/prototype/storefront.html) | Approved UI — open in a browser, click through all six pages |

---

## Conventions

- `declare(strict_types=1)` in every PHP file
- Actions over services: one class, one public `handle()`
- DTOs (`spatie/laravel-data`) at every boundary — no loose arrays
- Backed enums for every status
- Side effects go in event listeners, never inline in an Action
- Every external service sits behind an interface with a `Fake` implementation
- Conventional commits (`feat:`, `fix:`, `chore:`, `docs:`, `test:`)
- Branches: `main` (production) · `develop` (integration) · `feat/*`, `fix/*`

---

## Licence

Proprietary. All rights reserved.
