# Testing & quality gates

The target is not a coverage number. It is this: **you can change the pricing engine on a Friday
afternoon and know within four minutes whether you broke checkout.**

---

## The pyramid

| Layer | Tool | Covers | Runtime |
|---|---|---|---|
| **Unit** | Pest 5 | Actions, calculators, state machines, risk scoring, voucher rules, `Money`. No DB, no HTTP | < 15s |
| **Feature** | Pest + `RefreshDatabase` | Full request → response through real routes, middleware and database | ~90s |

The suite runs against **SQLite in memory** (`phpunit.xml`), so neither a
developer nor CI needs a database service to run `composer check`. Production
is PostgreSQL 17: once phase 1 lands migrations that use PG-specific types,
add a scheduled job that runs the same suite against real Postgres, so the
speed of SQLite never becomes a reason to miss a dialect bug.
| **Integration** | Pest + fakes | Courier booking, SMS, search indexing, remittance import — against `Fake` gateways and recorded fixtures | ~30s |
| **Browser** | Pest browser plugin | Checkout end-to-end, cart drawer, filters, admin order flow | ~4min |
| **Architecture** | Pest arch presets | Structural rules — see below | < 5s |

## Where effort goes

Test where being wrong costs money. Not everything deserves equal effort.

- **Pricing & vouchers** — every rule type, stacking, boundary cases (cart exactly at the
  free-delivery threshold), expiry, per-user caps.
- **Order state machine** — every legal transition asserted, and every *illegal* one asserted to
  throw. Small file, very high value.
- **COD risk scoring** — table-driven across factor combinations. Retuning weights tells you
  exactly what changed.
- **Remittance reconciliation** — the highest-consequence code in the system. Tested against
  real-shaped courier CSVs including partial batches, duplicate CNs and amount mismatches.
- **Inventory ledger** — concurrent decrements, oversell prevention, reservation expiry.
- **Checkout** — the happy path plus twelve failure paths: out of stock mid-checkout, price
  changed, voucher expired, OTP wrong, OTP expired, risk band blocked, courier unavailable.

## Architecture tests

See [`tests/Arch/ArchitectureTest.php`](../tests/Arch/ArchitectureTest.php). They enforce:

- The domain layer never depends on HTTP, Inertia, or a concrete gateway
- Controllers never query Eloquent directly
- Actions are `final readonly` with a single `handle()`
- No floating-point arithmetic anywhere near money
- Every enum is string-backed
- `declare(strict_types=1)` everywhere
- No `dd`, `dump`, `ray`, `var_dump` or `print_r` survives review

These run in under five seconds and prevent the structural rot that code review does not reliably
catch at month six. **If you want to weaken one, that is a design conversation — not an edit.**

## Mutation testing

```bash
composer test:mutate
```

Runs on `Domain/Pricing` and `Domain/Cod` only, gated at **75% MSI**. Line coverage proves code
*ran*; mutation testing proves your assertions would actually *catch a bug*. Running it across the
whole suite is a waste of CI minutes — running it on the code that handles money is not.

## Coverage gates

| Path | Minimum |
|---|---|
| `app/Domain/**` | 90% |
| `app/Http/**` | 80% |
| Overall | 85% |
| `app/Filament/**` | excluded — browser tests cover the flows that matter |

Type coverage: **95%** minimum (`composer test:type`).

---

## Static analysis

| Gate | Tool | Setting |
|---|---|---|
| Static analysis | Larastan / PHPStan | Level 8 · **level 9** on `app/Domain` |
| Code style | Laravel Pint | Laravel preset + strict rules |
| Refactors | Rector | PHP 8.4 + code quality + dead code sets |
| Security | `composer audit`, `npm audit` | Fails on high/critical |
| Frontend types | `vue-tsc` | Strict, no `any` |
| Frontend lint | ESLint | Vue 3 recommended + a11y |
| N+1 queries | `Model::preventLazyLoading()` | Throws in dev and test |
| Performance | Lighthouse CI | LCP < 1.2s, JS < 130KB on product pages |

## CI pipeline

Three parallel jobs on every PR (`.github/workflows/ci.yml`):

```
├─ static-analysis   Pint → PHPStan 8 → PHPStan 9 (domain) → Rector → audit
├─ frontend          vue-tsc → ESLint → production build
└─ tests             arch tests → suite with coverage → type coverage
                     (mutation testing runs on main only)
```

Roughly **six minutes** for a PR. `composer check` runs the same gates locally — if it is green on
your machine, CI will be green.

## Non-negotiables

- `declare(strict_types=1)` in every PHP file
- No `mixed` return types in `app/Domain`
- Every migration has a working `down()`
- Every queued job is idempotent with explicit `$tries` and `$backoff`
- Every external call has a timeout
- No test ever calls a live courier, SMS or payment API
