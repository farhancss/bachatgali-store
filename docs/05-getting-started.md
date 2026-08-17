# Getting started

## Requirements

- Docker & Docker Compose (everything else runs in containers)
- PHP 8.4 and Composer 2 on the host (for `composer install` and editor tooling)
- Node 22+

## First run

```bash
cp .env.example .env
docker compose up -d          # postgres, redis, typesense, mailpit, app
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
```

| Service | URL |
|---|---|
| Storefront | http://localhost:8000 |
| Admin (Filament, phase 1) | http://localhost:8000/admin |
| Mailpit | http://localhost:8025 |
| Typesense | http://localhost:8108 |
| Horizon (phase 3) | http://localhost:8000/horizon |
| Pulse | http://localhost:8000/pulse |

## Running without Docker

If you already have PostgreSQL 17 and Redis on the host, the containers are
optional. Create the role and databases once (the second one is what
`phpunit.xml` points the suite at):

```bash
createuser bachatgali --createdb --pwprompt
createdb --owner=bachatgali bachatgali
createdb --owner=bachatgali bachatgali_test
```

Then serve the app with the built-in server instead of Octane:

```bash
php artisan serve & npm run dev
```

Two host-specific settings to check in `.env`:

- **`REDIS_CLIENT`** — the Docker image ships `ext-redis`, a Homebrew PHP
  usually does not. Set `REDIS_CLIENT=predis` if `php -m | grep redis` comes
  back empty; `predis/predis` is installed for exactly this case.
- **`SCOUT_DRIVER`** — leave it as `typesense` only if Typesense is actually
  running. Nothing in phase 0 indexes anything, so `SCOUT_DRIVER=null` is fine
  until phase 2.

**No courier credentials required.** `COURIER_DEFAULT=fake` binds an in-memory gateway that
behaves like a real courier, including failure modes. The entire test suite uses it.

## Before every push

```bash
composer check
```

If that passes, CI passes. It runs Pint, PHPStan (level 8, and level 9 on `app/Domain`), Rector
dry-run, architecture tests, the test suite with coverage, and type coverage.

Optional git hook:

```bash
cat > .git/hooks/pre-push <<'HOOK'
#!/bin/sh
composer check || exit 1
HOOK
chmod +x .git/hooks/pre-push
```

---

## How to add a feature

Follow the worked slice in `app/Domain/Cod/Actions/ScoreCodRisk.php` — it is deliberately the
reference implementation for everything else.

1. **Model the data.** Migration + Eloquent model in `app/Domain/<Context>/Models`. Money columns
   are `bigInteger` paisa and cast with `MoneyCast`. Statuses are backed enums.
2. **Define the boundary.** A DTO in `DataObjects/` using `spatie/laravel-data`. This is what the
   request validates into and what the Action receives — never a loose array.
3. **Write the Action.** One `final readonly class` in `Actions/` with a single `handle()`. No
   HTTP, no Inertia, no direct courier calls. If it needs the outside world, inject an interface.
4. **Fire an event for side effects.** Notifications, courier booking, search reindexing and
   analytics all belong in listeners. Keep the Action about the decision, not the consequences.
5. **Test it.** Unit test the Action with no database. Feature test the route. If it touches money
   or COD risk, make it table-driven so retuning tells you exactly what changed.
6. **Wire the controller.** Validate via FormRequest, call the Action, return a response. If a
   controller grows an `if`, the logic belongs in the Action.
7. **Run `composer check`.**

### Choosing Blade or Inertia

| Building | Use | Because |
|---|---|---|
| A page Google should index | Blade | Server-rendered, cacheable, no JS dependency |
| A page behind login, or stateful | Inertia | App-like, never indexed |
| An interactive widget on a Blade page | Vue island | Same component, mounted individually |

If you are unsure, ask: *would it hurt if this page never appeared in search results?* If yes, it
is Blade.

---

## Conventions that matter

**Money.** Always `Money`, always integer paisa. `Money::fromRupees(2_500)` not `2500.00`. An
architecture test fails the build if `round`, `floor`, `ceil` or `floatval` appear anywhere in the
pricing or COD domains.

**Enums.** Every status is a string-backed enum with behaviour on it (`$band->canDispatch()`), not
a bare string compared in three places.

**No lazy loading.** `Model::preventLazyLoading()` is on outside production, so an N+1 query throws
in development and in tests. Eager load explicitly.

**Naming.** Actions are verbs (`PlaceOrder`, `ScoreCodRisk`, `ReconcileRemittance`). Queries are
nouns ending in `Query`. Events are past tense (`OrderPlaced`). Nothing is called `Manager`,
`Helper` or `Util`.

**Commits.** Conventional commits. `feat(cod): score risk on incomplete addresses`.
