# COD Commerce Platform — Architecture & Build Plan
### Laravel 13 · Inertia v3 · Vue 3 · Filament · PostgreSQL
**Replacing WooCommerce · Cash on delivery only · Service-based architecture, tested and statically verified**

> Project: **Bachat Gali** (`bachatgali/store`). Brand strings live in `config/bachatgali.php`.
> Rendering is **hybrid**: SEO-critical catalog pages are server-rendered Blade; app-like flows run on Inertia.
> Admin is **Filament**; the storefront is entirely custom.

---

## 1. Why this stack, honestly

Moving from Next.js/NestJS to Laravel/Inertia is a real trade, not a lateral move. Here's the actual ledger.

**What you gain**

- **One codebase, one deploy, one language.** No API contract to keep in sync, no CORS, no duplicated validation, no separate Node service to monitor. For a team of one to four, this is the single biggest velocity factor.
- **Hiring.** The PHP/Laravel talent pool in this market is deep and affordable; senior Next.js + NestJS developers are scarce and expensive. Over a two-year horizon this matters more than any framework benchmark.
- **Filament.** A production-grade admin — tables, filters, bulk actions, form builders, dashboards — largely configured rather than written. This removes roughly a fifth of the total build.
- **Batteries included.** Queues (Horizon), scheduling, mail, notifications, storage, policies, events, migrations, factories are all first-party and consistent. In the Node stack each of those is a library decision you own forever.
- **PHP-to-PHP migration.** Your WooCommerce data and mental model carry over. The migration script reads the same MySQL tables your team already understands.
- **Cheaper to run.** One app server instead of a storefront host plus an API host plus a worker host.

**What you give up — and how we deal with it**

| Loss | Reality | Mitigation in this plan |
|---|---|---|
| No ISR / edge-rendered pages | Next.js's incremental static regeneration genuinely has no Laravel equivalent | Full-page response cache with cache tags + Cloudflare edge cache, invalidated by domain events on price/stock change. Practically equivalent for a catalog store |
| SPA pages aren't crawlable by default | Inertia renders client-side unless you add SSR | Hybrid rendering — catalog pages never touch Inertia. See section 4 |
| Less granular scaling | One app scales as one unit | Octane + horizontal app replicas; queue workers scale separately. More than sufficient at this size |
| Smaller "modern frontend" ecosystem | Fewer ready-made React commerce components | The prototype is already hand-built, so we weren't using them anyway |

**The blunt version:** the Node stack wins on raw edge performance at very large scale. Laravel wins on time-to-market, cost, and maintainability at your scale. For a COD marketplace where the hard problems are RTO rates, courier reconciliation and merchandising — not p99 latency at ten million requests — Laravel is the better bet. I'd have recommended it if you'd asked before I wrote the first plan.

---

## 2. System architecture

```
                        ┌────────────────────────────────────────┐
                        │       Cloudflare — CDN / WAF           │
                        │  edge cache · images · bot · rate lim  │
                        └────────────────────┬───────────────────┘
                                             │
                        ┌────────────────────▼───────────────────┐
                        │   Laravel 13 on Octane (FrankenPHP)    │
                        │        single deployable app           │
                        ├────────────────────────────────────────┤
                        │  HTTP LAYER                            │
                        │   ├─ Blade controllers   → catalog/SEO │
                        │   ├─ Inertia controllers → cart, auth, │
                        │   │                        checkout,   │
                        │   │                        account     │
                        │   ├─ Filament panel      → admin/ops   │
                        │   └─ API controllers     → webhooks,   │
                        │                            mobile app  │
                        ├────────────────────────────────────────┤
                        │  APPLICATION LAYER  (services/actions) │
                        │   PlaceOrderAction · ScoreCodRiskAction│
                        │   BookShipmentAction · ApplyVoucher…   │
                        │   — thin, single-purpose, unit-tested  │
                        ├────────────────────────────────────────┤
                        │  DOMAIN LAYER  (per bounded context)   │
                        │  Catalog · Pricing · Inventory · Cart  │
                        │  Ordering · Cod · Delivery · Customer  │
                        │  Content · Search · Notification       │
                        │   models · enums · states · events     │
                        ├────────────────────────────────────────┤
                        │  INFRASTRUCTURE  (driver contracts)    │
                        │  CourierGateway · SmsGateway ·         │
                        │  SearchEngine · MediaStore             │
                        │   — interfaces, swappable, fakeable    │
                        └───┬──────────┬──────────┬──────────┬───┘
                            │          │          │          │
                   ┌────────▼──┐ ┌─────▼────┐ ┌───▼─────┐ ┌──▼────────┐
                   │PostgreSQL │ │  Redis   │ │Typesense│ │ R2 / S3   │
                   │  17       │ │cache·queue│ │ search  │ │  media    │
                   └───────────┘ └─────┬────┘ └─────────┘ └───────────┘
                                       │
                              ┌────────▼─────────┐
                              │ Horizon workers  │  courier sync · SMS ·
                              │ (separate procs) │  WhatsApp · reindex ·
                              └──────────────────┘  remittance import
```

### The rule that keeps this clean

**Dependencies point inward only.** Infrastructure depends on Domain; Domain depends on nothing. A controller may not touch Eloquent directly, and a domain class may not know that HTTP exists. This is enforced by architecture tests in CI (section 7), not by discipline alone — discipline erodes at month four, tests don't.

---

## 3. Service-based architecture — how the code is actually laid out

```
app/
├── Domain/                          ← business logic, framework-light
│   ├── Catalog/
│   │   ├── Models/                  Product, Variant, Category, Brand
│   │   ├── Enums/                   ProductType, StockState
│   │   ├── Actions/                 CreateProduct, SyncVariantStock
│   │   ├── DataObjects/             ProductData, VariantData  (spatie/laravel-data)
│   │   ├── Events/                  ProductPriceChanged, StockDepleted
│   │   ├── Queries/                 ProductListQuery, RelatedProductsQuery
│   │   └── Rules/                   SkuIsUnique
│   ├── Pricing/                     PriceCalculator, VoucherEngine, DiscountRule
│   ├── Inventory/                   StockLedger, ReserveStock, ReleaseStock
│   ├── Cart/                        CartService, AddItem, MergeGuestCart
│   ├── Ordering/
│   │   ├── Models/                  Order, OrderItem, OrderStatusHistory
│   │   ├── States/                  Pending → Confirmed → Packed → …  (spatie/laravel-model-states)
│   │   ├── Actions/                 PlaceOrder, CancelOrder, RefundOrder
│   │   └── Events/                  OrderPlaced, OrderDelivered, OrderReturned
│   ├── Cod/                         ← the money-critical context
│   │   ├── Actions/                 ScoreCodRisk, VerifyPhoneOtp, ReconcileRemittance
│   │   ├── Models/                  CodCollection, RemittanceBatch, RiskProfile
│   │   └── Enums/                   RiskBand, RemittanceStatus
│   ├── Delivery/                    Shipment, TrackingEvent, BookShipment, RateCalculator
│   ├── Customer/                    Customer, Address, LoyaltyLedger, Referral
│   ├── Content/                     Page, Banner, HomepageSection, MenuItem
│   ├── Search/                      IndexProduct, SearchProducts, FacetBuilder
│   └── Notification/                OrderNotifier, WhatsAppChannel, SmsChannel
│
├── Infrastructure/                  ← the outside world, behind contracts
│   ├── Courier/
│   │   ├── Contracts/CourierGateway.php
│   │   ├── PostEx/PostExGateway.php
│   │   ├── Leopards/LeopardsGateway.php
│   │   ├── Tcs/TcsGateway.php
│   │   └── Fake/FakeCourierGateway.php        ← used by the whole test suite
│   ├── Sms/       {Contracts, Providers, FakeSmsGateway}
│   ├── Search/    {TypesenseEngine, FakeSearchEngine}
│   └── Media/     {CloudflareImages, LocalMediaStore}
│
├── Http/                            ← thin. genuinely thin.
│   ├── Controllers/
│   │   ├── Catalog/                 → Blade views (SEO pages)
│   │   ├── Shop/                    → Inertia pages (cart, checkout, account)
│   │   └── Webhooks/                → courier callbacks
│   ├── Requests/                    FormRequest validation only
│   ├── Middleware/
│   └── ViewModels/                  shapes data for Blade/Inertia, no logic
│
├── Filament/                        ← admin panel resources & widgets
│   ├── Resources/                   OrderResource, ProductResource, …
│   ├── Widgets/                     RtoRateChart, CashInTransitStat, …
│   └── Pages/                       RemittanceReconciliation
│
├── Jobs/                            queue work: BookShipmentJob, ReindexProductJob
├── Listeners/                       react to domain events
└── Providers/                       container bindings (contracts → implementations)
```

### The patterns, and why each one is there

**Actions over services-with-many-methods.** One class, one public method (`handle()`), one job. `PlaceOrderAction` does exactly one thing and is trivially unit-testable. A `OrderService` with fourteen methods becomes a dumping ground by month three — this is the most common way "service layer" degrades into "second controller".

**Controllers stay under ~20 lines.** Validate via FormRequest → call an Action → return a view or Inertia response. If a controller grows an `if`, the logic belongs in the Action.

```php
final class PlaceOrderController
{
    public function __construct(private PlaceOrderAction $placeOrder) {}

    public function __invoke(PlaceOrderRequest $request): RedirectResponse
    {
        $order = $this->placeOrder->handle(
            CheckoutData::from($request->validated()),
            $request->user(),
        );

        return to_route('orders.confirmation', $order);
    }
}
```

**DTOs at every boundary** (`spatie/laravel-data`). Arrays get passed around and mutated; typed objects don't. The same `ProductData` class validates the request, types the Action's input, and serialises to an Inertia prop with generated TypeScript types on the Vue side — one definition, no drift.

**Enums for every status.** `OrderStatus::OutForDelivery`, `RiskBand::High`. String statuses are how you end up with `'delivered'`, `'Delivered'` and `'DELIVERED'` in the same column.

**Explicit state machine for orders** (`spatie/laravel-model-states`). Legal transitions are declared, illegal ones throw. `Pending → Delivered` skipping `Packed` becomes impossible rather than merely unlikely.

**Events for side effects, never inline.** `OrderPlaced` fires; listeners send WhatsApp, decrement stock, queue the courier booking, update search. Adding a new side effect means adding a listener, not editing `PlaceOrderAction`. This is what keeps the core action stable and cheap to test.

**Money is `int` in paisa, always.** Wrapped in a `Money` cast. Floats do not touch currency anywhere in the codebase — an architecture test asserts it.

**Every external service sits behind an interface.** `CourierGateway` has PostEx, Leopards, TCS and Fake implementations. The entire test suite runs against Fake; no test ever hits a real courier. Adding a fourth courier is a new class and a config line.

**Repositories only where they earn it.** Eloquent is already a decent data layer — wrapping every model in a repository is ceremony. Query objects (`ProductListQuery`) are used instead for complex, reusable reads.

---

## 4. Rendering strategy — the important decision

Inertia is excellent for app-like screens and wrong for indexable catalog pages. So we don't use it for those.

| Route | Rendering | Reasoning |
|---|---|---|
| `/` home | **Blade** + full-page cache | Fully server-rendered, cached at the edge. TTFB under 100ms |
| `/c/{category}` listing | **Blade** + cached fragments | Facets are links, not JS state. Crawlable, shareable, back-button correct |
| `/p/{slug}` product | **Blade** + cache tags | The single most SEO-critical page. Complete HTML, JSON-LD inline, zero JS dependency for indexing |
| `/search` | **Blade** shell + Inertia island | Server-renders the first result page, then hydrates for instant filtering |
| `/cart`, `/checkout` | **Inertia** | Stateful, private, never indexed. Inertia is ideal here |
| `/account/*` | **Inertia** | App-like, authenticated |
| `/admin/*` | **Filament** | Internal tooling |

Interactive pieces on Blade pages (cart drawer, wishlist toggle, image gallery, quantity picker, mega-menu) are **mounted Vue islands** — the same `.vue` components the Inertia pages use, mounted individually. One component library, two mounting strategies, no duplication.

**What this buys you:** product pages that are pure server-rendered HTML with structured data, cacheable at the CDN, and fast on a mid-range Android on a 3G connection — which is what your actual customers are using. And no Node SSR process to operate.

---

## 5. Technology choices

### Core
| Concern | Choice | Reason |
|---|---|---|
| Framework | **Laravel 13** (released Mar 2026, supported to Mar 2028) | Current major, long runway |
| Runtime | **Laravel Octane + FrankenPHP** | Persistent workers; 3–5× throughput over PHP-FPM |
| PHP | **8.4+**, `declare(strict_types=1)` everywhere | Property hooks, asymmetric visibility, better enums |
| Frontend | **Inertia v3 + Vue 3** (Composition API, `<script setup>`) | Deferred props, prefetching and polling ship in the box — the things that made SPAs feel slow are largely solved |
| Types | **TypeScript strict** + `vue-tsc` in CI | Types generated from PHP DTOs via `typescript-transformer` |
| Styling | **Tailwind CSS v4** + CSS custom properties | Drives the dark/light theming already in the prototype |
| Build | **Vite 7** + code splitting per Inertia page | Only ship what the route needs |
| Admin | **Filament v4** | Orders, catalog, inventory, COD dashboards |
| DB | **PostgreSQL 17** | JSONB attributes, partial indexes, materialised views for reports |
| Cache / Queue | **Redis 7** + **Horizon** | Cache, sessions, locks, queues with a real monitoring UI |
| Search | **Laravel Scout + Typesense** | Typo-tolerant faceted search, sub-50ms, self-hostable |
| Media | **Spatie Media Library** + Cloudflare Images | Conversions, responsive sets, AVIF/WebP |
| PDFs | **Spatie Laravel PDF** (Browsershot) | Invoices, load sheets, packing slips |
| Auth | **Laravel Fortify**, phone-OTP first + Google OAuth | Phone-first matches how people actually log in here |
| Observability | **Sentry** + **Laravel Pulse** + OpenTelemetry | Pulse gives slow queries and queue depth out of the box |

### Key packages
`spatie/laravel-data` · `spatie/laravel-model-states` · `spatie/laravel-permission` · `spatie/laravel-medialibrary` · `spatie/laravel-activitylog` (audit trail) · `spatie/laravel-responsecache` · `spatie/laravel-sitemap` · `spatie/schema-org` (JSON-LD) · `laravel/scout` · `laravel/horizon` · `laravel/pulse` · `filament/filament` · `tightenco/ziggy` (routes in JS)

**Deliberately not used:** a generic e-commerce package. Bagisto/Aimeos would give you 70% for free and then fight you on the other 30% — which is exactly the COD risk logic and courier reconciliation that makes or breaks this business. Same trap as WooCommerce plugins, one abstraction layer higher.

---

## 6. Feature set

*(Unchanged in scope from the previous plan — restated here in Laravel terms so it's one document.)*

### 6.1 Catalog & merchandising
Simple / variable / bundle product types · nested categories, brands, collections · rule-based dynamic collections · per-variant SKU, barcode, weight, dimensions, price, stock · multi-image galleries with zoom · typed custom attributes driving facets · related, cross-sell, upsell, frequently-bought-together · recently viewed, wishlist, compare · stock states incl. low-stock threshold, backorder, pre-order · bulk CSV/XLSX import-export with dry-run and a validation report.

### 6.2 Search & discovery
Instant search-as-you-type with typo tolerance and Urdu/English synonyms · faceted filtering on price, brand, category, attributes, rating, discount band, availability — all URL-driven and indexable · sorting by relevance, newest, price, popularity, rating, discount · zero-result query reporting · curated pinning and boost rules.

### 6.3 Cart & checkout
Guest cart merging into the account on login · stock and price re-validation on entering checkout · voucher engine (%, fixed, free delivery, BOGO, tiered, first-order, category-scoped, usage caps, stackable rules, auto-apply) · one-page COD checkout, phone-first · saved addresses · shipping rules by city, zone, weight and cart value · free-delivery thresholds · abandoned-cart capture and recovery.

### 6.4 Orders & fulfilment
Explicit state machine: pending → confirmed → packed → shipped → out for delivery → delivered / returned / cancelled · partial fulfilment and split shipments · courier integrations (PostEx, Leopards, TCS, Trax, M&P) with one-click booking, CN generation, printable load sheets and webhook status sync · public tracking by order ID + phone, no login · RMA flow with reason codes and approval · invoice and packing-slip PDFs · internal vs. customer-visible notes · complete audit log of every transition.

### 6.5 Customer accounts
Phone-OTP login primary, Google OAuth secondary · dashboard for orders, tracking, addresses, wishlist, reviews, returns · loyalty points with earn-on-delivery and expiry · referral codes with attribution · guest checkout with post-purchase account claim.

### 6.6 Reviews & UGC
Verified-purchase badges · photo and video reviews · rating breakdown · helpful votes · moderation queue with spam filtering · automated review requests N days after delivery · per-product Q&A.

### 6.7 Content & marketing
Filament-managed pages, blog, banners and homepage sections — no deploy needed to change merchandising · drag-ordered homepage section builder · flash-sale scheduler with automatic price rollback · newsletter capture and segmentation · Meta / GA4 / TikTok via **server-side** Conversions API so ad-blockers don't destroy attribution · auto-generated sitemaps and product feeds for Google Merchant Center, Meta Catalog and TikTok Shop.

### 6.8 Admin (Filament)
Role-based permissions: owner, manager, support, packer, content editor · realtime dashboard for revenue, AOV, conversion, top products, low stock, **RTO rate**, courier performance · bulk order and product operations · inventory with reason-coded adjustments, purchase orders, suppliers, multi-warehouse ready · customer profiles with LTV and RTO history · exportable reports · full activity log.

### 6.9 COD operations — where the real risk lives

COD is not "checkout minus payment". It moves the entire financial risk onto you, and the software has to earn it back.

**Before dispatch**
- Mandatory OTP verification of the phone number. Removes most junk orders and costs almost nothing
- Address quality score — incomplete address, no landmark, or a historically bad area routes to a confirmation call
- Per-customer order-value ceiling, raised automatically as delivery history builds
- Blocklist by phone and address for repeat refusers — soft-flag requiring approval, not a hard ban
- Duplicate-order detection (same items, same number, minutes apart)
- **RTO risk score** combining past refusals, city, order value, category, first-time vs. returning, and time of day. High-risk orders enter a confirmation queue instead of going straight to dispatch

**At dispatch**
- Courier selected automatically per city by measured success rate and cost, not a fixed default
- Packing photo/video capture on high-value orders — settles disputes instantly
- Exact cash amount on the label and in the customer's WhatsApp message, so riders aren't stuck making change

**After delivery**
- Courier remittance import reconciled automatically against delivered orders, with a variance report for mismatches
- **Cash-in-transit ageing** — collected but not yet remitted. This is working capital and it must be visible daily
- COD fee and RTO cost attributed per order, so product-level profitability is true rather than assumed

**Dashboard metrics from day one:** RTO rate overall and by city / product / courier · delivery success rate per courier · average remittance cycle · cash in transit · net margin after RTO and shipping. A store can look profitable on gross sales and lose money entirely to RTO — this dashboard is how you see it before it becomes a problem.

### 6.10 Security & compliance
Rate limiting per IP and per account · Cloudflare Turnstile on auth and checkout · CSRF, strict CSP, HSTS · parameterised queries throughout · Policies on every model, checked in Filament too · encrypted PII at rest · secrets in the vault, never in code · data export and account deletion endpoints. **No card data exists anywhere in the system** — COD-only removes PCI scope entirely.

---

## 7. Testing strategy

The target is not a coverage number. It's this: **you can change the pricing engine on a Friday afternoon and know within four minutes whether you broke checkout.**

### The pyramid

| Layer | Tool | What it covers | Count (est.) | Runtime |
|---|---|---|---|---|
| **Unit** | Pest 4 | Actions, calculators, state machines, risk scoring, voucher rules, `Money`. No DB, no HTTP | ~450 | < 15s |
| **Feature / HTTP** | Pest + `RefreshDatabase` | Full request → response through real routes, middleware, DB. The bulk of the suite | ~320 | ~90s |
| **Integration** | Pest + fake gateways | Courier booking, SMS, search indexing, remittance import — against Fakes and recorded fixtures | ~70 | ~30s |
| **Browser** | Pest v4 browser testing | Checkout end-to-end, cart drawer, filters, admin order flow. Real browser, real JS | ~25 | ~4min |
| **Architecture** | Pest arch presets | Structural rules — see below | ~30 | < 5s |

### What gets tested hardest

The rule: **test where being wrong costs money.** Not everything deserves equal effort.

- **Pricing & vouchers** — every rule type, stacking, edge cases at boundaries (cart exactly at the free-delivery threshold), expiry, per-user caps. Property-based tests where the rules interact.
- **Order state machine** — every legal transition asserted, and every *illegal* one asserted to throw. This is a small, cheap, extremely high-value test file.
- **COD risk scoring** — table-driven tests over risk factor combinations, with the expected band asserted. When you tune the model later, these tests tell you what changed.
- **Remittance reconciliation** — the highest-consequence code in the system. Tested against real-shaped courier CSVs including the ugly cases: partial batches, duplicate CNs, amount mismatches, returned-but-marked-delivered.
- **Inventory ledger** — concurrent stock decrements under load, oversell prevention, reservation expiry.
- **Checkout happy path + 12 failure paths** — out of stock mid-checkout, price changed, voucher expired, OTP wrong, OTP expired, address rejected, risk score too high, courier down.

### Architecture tests — the rules that keep the design intact

```php
arch('domain does not depend on HTTP')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate\Http', 'App\Http', 'Inertia']);

arch('controllers do not touch Eloquent directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Builder');

arch('actions are final and single-purpose')
    ->expect('App\Domain\*\Actions')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('no floats near money')
    ->expect('App\Domain\Pricing')
    ->not->toUse(['floatval', 'round']);

arch('strict types everywhere')
    ->expect('App')->toUseStrictTypes();

arch('no debugging left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();
```

These run in under five seconds and prevent the slow architectural rot that no code review catches consistently at month six.

### Mutation testing

`pest --mutate` on the `Domain/Pricing` and `Domain/Cod` namespaces, gated at **≥ 75% MSI**. Line coverage tells you code *ran*; mutation testing tells you your assertions would actually *catch a bug*. Only worth the runtime on the money-critical namespaces — running it suite-wide is a waste.

### Coverage gates
- `app/Domain/**` — **90% minimum**, build fails below
- `app/Http/**` — 80% minimum
- Overall — 85% minimum
- Filament resources — excluded (testing framework config has poor returns; the browser tests cover the flows that matter)

### Test data
Model factories with meaningful states (`Product::factory()->outOfStock()`, `Order::factory()->highRiskCod()`) · a `DemoSeeder` producing a realistic catalog for local dev and staging · courier fixtures captured from real sandbox responses, not invented.

---

## 8. Static analysis & quality gates

Nothing merges without passing all of it. Every check is also a local `composer` script so it fails on your machine before CI.

| Gate | Tool | Setting |
|---|---|---|
| Static analysis | **Larastan / PHPStan** | **Level 8** at launch, level 9 on `app/Domain` |
| Type coverage | **Pest type-coverage** | ≥ 95% — every parameter, return and property typed |
| Code style | **Laravel Pint** | Laravel preset + strict rules, `--test` in CI |
| Refactors & upgrades | **Rector** | Laravel + PHP 8.4 sets, dry-run in CI |
| Security | `composer audit`, `npm audit` | Fails on high/critical |
| Frontend types | **vue-tsc** | Strict, no `any` |
| Frontend lint | **ESLint** + Prettier | Vue 3 recommended + a11y plugin |
| Unused code | **Composer Unused** / Knip | Warn only |
| DB queries | `Model::preventLazyLoading()` | Throws on N+1 in dev and test |
| Performance | **Lighthouse CI** | Budget gate: LCP < 1.2s, JS < 130KB on PDP |

### CI pipeline

```
push / PR
 ├─ 1. install (cached composer + npm)
 ├─ 2. pint --test          ~10s   ┐
 ├─ 3. phpstan              ~40s   ├ run in parallel
 ├─ 4. rector --dry-run     ~30s   │
 ├─ 5. vue-tsc + eslint     ~35s   ┘
 ├─ 6. pest --parallel --coverage      ~2min
 ├─ 7. pest --mutate (Domain/Pricing, Domain/Cod)   ~3min  [main only]
 ├─ 8. pest browser tests   ~4min
 ├─ 9. lighthouse-ci        ~2min
 └─ 10. deploy to staging (auto) → production (manual approval)
```

Roughly **six minutes** for a PR, twelve on main. Pre-commit hooks run Pint and PHPStan on changed files only, so most failures never reach CI.

### Non-negotiables
- `declare(strict_types=1)` in every PHP file
- No `mixed` return types in `app/Domain`
- Every migration has a working `down()`, verified by a rollback test in CI
- Every queued job is idempotent and has an explicit `$tries` / `$backoff`
- Every external call has a timeout and a circuit breaker
- Conventional commits → automated changelog

---

## 9. Performance strategy

**Targets:** TTFB < 120ms cached / < 300ms uncached · LCP < 1.2s on 4G · INP < 100ms · CLS < 0.05 · Lighthouse mobile ≥ 95 · JS on product page < 130KB gzipped.

1. **Octane + FrankenPHP.** The framework boots once, not per request. Single biggest server-side win.
2. **Full-page response cache with tags** on catalog pages, invalidated by `ProductPriceChanged` / `StockDepleted` events. Cloudflare holds the same response at the edge. A product page under normal conditions never reaches PHP.
3. **Blade for catalog = no hydration cost.** Vue islands are a few KB each and load only where used.
4. **Inertia v3 prefetching** on hover/viewport for cart, checkout and account — navigation feels instant.
5. **Deferred props** for below-the-fold data (reviews, related products) so the main response ships immediately.
6. **N+1 elimination enforced** — `preventLazyLoading` throws in dev and test, so N+1 queries are impossible to merge.
7. **Query discipline** — indexes on every filter and sort column, cursor pagination, materialised views for heavy reports, `EXPLAIN ANALYZE` on anything over 50ms.
8. **Images** — Media Library conversions to AVIF/WebP, exact-size responsive sets, `loading="lazy"` on everything except the LCP image, aspect-ratio boxes so CLS stays at zero. Images are ~70% of an ecommerce page's weight; this is where the win actually lives.
9. **One variable font**, subsetted (Latin + Urdu split), self-hosted, preloaded.
10. **Lighthouse budget in CI** — a PR that regresses performance fails. Speed you don't defend automatically is speed you lose within six weeks.

---

## 10. SEO strategy

- **Server-rendered HTML for everything indexable.** No JS execution required to see content.
- **JSON-LD** via `spatie/schema-org`: `Product` (price, availability, rating, brand, GTIN), `BreadcrumbList`, `Organization`, `WebSite` + Sitelinks Search Box, `FAQPage`, `Review`.
- **URL taxonomy:** `/c/{category}/{sub}`, `/p/{slug}`, facets as canonical-controlled query params. Faceted-navigation rules index the valuable combinations and `noindex,follow` the long tail to prevent index bloat.
- **Canonicals, hreflang (en/ur), pagination** handled centrally in a Blade layout, not per-page.
- **Auto-generated sitemaps** split by type, regenerated nightly and on significant change, auto-pinged.
- **Per-page metadata** editable in Filament with sensible auto-generated fallbacks, so nothing ever ships blank.
- **Dynamic OG images** rendered by Browsershot and cached — product image, name and price in the share card.
- **Migration-critical:** a complete 301 map from every existing WooCommerce URL. Skipping this is the number one way a replatform loses rankings. It ships *with* launch, not after.

---

## 11. Prototype → component map

The HTML prototype translates directly. Nothing needs redesigning.

| Prototype section | Becomes | Notes |
|---|---|---|
| Announcement bar, header, nav strip | `layouts/storefront.blade.php` | Cart count via a small Vue island |
| Category icon rail | `<x-catalog.category-rail>` Blade component | Cached fragment, pure HTML |
| Hero carousel | `HeroCarousel.vue` island | Slides managed in Filament |
| Voucher strip | `<x-marketing.voucher-strip>` + `VoucherCollect.vue` | Collect action posts to Inertia endpoint |
| Flash sale block + countdown | `<x-catalog.flash-sale>` + `Countdown.vue` | Server supplies the end timestamp; no client clock trust |
| Product card | `<x-catalog.product-card>` Blade **and** `ProductCard.vue` | Two thin renderers over one `ProductCardData` DTO |
| Product grid / feed | Blade loop, `Load more` via Inertia partial reload | Merge props for infinite scroll |
| COD parallax band | `<x-marketing.cod-band>` | Static, cached |
| PLP filter sidebar | `<x-catalog.filters>` | Links, not JS state — crawlable and shareable |
| PDP gallery | `ProductGallery.vue` island | Zoom, thumbnails, keyboard nav |
| PDP variant selector | `VariantPicker.vue` island | Emits to the buy box; price/stock update without reload |
| PDP buy box | `BuyBox.vue` island | Delivery estimate fetched as a deferred prop |
| Cart drawer | `CartDrawer.vue` | Global, mounted in the layout, Pinia-backed |
| Checkout | `Pages/Checkout/Index.vue` — **full Inertia page** | Multi-step state, OTP, no reloads |
| Order tracking | `Pages/Orders/Track.vue` | Public route, order ID + phone |
| Account dashboard | `Pages/Account/*.vue` | Inertia, authenticated |
| Admin | Filament resources | Not built from the prototype |

Design tokens (the CSS custom properties driving dark/light) move into `resources/css/tokens.css` and are consumed by both Blade and Vue — one source of truth for theming.

---

## 12. Delivery roadmap

Sessions are my working units; calendar assumes you review between them.

| Phase | Deliverable | Sessions |
|---|---|---|
| **0 — Foundation** | Laravel 13 + Octane, Docker, Postgres, Redis, Inertia+Vue+TS, Tailwind v4 with the prototype's tokens, Pest, PHPStan L8, Pint, Rector, full CI pipeline, domain folder skeleton, architecture tests | 1–2 |
| **1 — Catalog domain** | Migrations & models, DTOs, Actions, Media Library, Scout/Typesense indexing, Filament catalog resources, factories, unit + feature tests | 3 |
| **2 — Storefront (Blade)** | Layout, home, category/PLP with facets, PDP, search, Vue islands, response caching, JSON-LD, sitemaps | 3–4 |
| **3 — Cart & COD checkout** | Cart service, voucher engine, shipping rules, Inertia checkout, OTP verification, risk scoring, order placement, state machine | 3 |
| **4 — Orders & fulfilment** | Order lifecycle, Filament order management, courier gateways + webhooks, load sheets, invoice PDFs, WhatsApp/SMS notifications | 3–4 |
| **5 — COD operations** | Remittance import & reconciliation, cash-in-transit ageing, RTO dashboards, blocklists, confirmation queue | 2 |
| **6 — Accounts & UGC** | Auth, customer dashboard, reviews, returns/RMA, loyalty, referrals | 2 |
| **7 — Content & marketing** | Filament CMS, homepage builder, banners, flash-sale scheduler, feeds, server-side pixels | 2 |
| **8 — Migration** | WooCommerce export → transform → import (products, customers, orders, reviews), **301 redirect map**, reconciliation counts | 1–2 |
| **9 — Hardening** | Browser test suite, k6 load test, mutation testing gates, security review, backup/restore drill, runbooks, staff training material | 2–3 |

**Total: 22–27 sessions.** At 3–4 sessions a week with prompt review, that's **6–8 weeks to a store taking real orders** — versus roughly 14 weeks for a human team building the same thing.

**What sets the calendar and isn't me:** courier merchant accounts (1–3 weeks, start now — they gate phase 4) · your catalog data and product photography · your review turnaround · two weeks of real deliveries to validate COD and remittance end-to-end · hosting, domain and DNS decisions.

---

## 13. Running cost (monthly, USD)

| Item | Cost |
|---|---|
| App server — 4 vCPU / 8GB, Octane (Hetzner/Contabo) | $20–40 |
| Managed PostgreSQL with PITR backups | $20–30 |
| Redis | $0–15 (or co-located) |
| Typesense | $0 self-hosted – $25 managed |
| Cloudflare (CDN + Images) | $0–25 |
| Object storage (R2, zero egress) | $5–10 |
| SMS + WhatsApp Business API | $15–50, volume-based |
| Sentry | $0–26 |
| **Total** | **≈ $60–220/mo** |

Lower than the Node stack at the same traffic, because it's one app instead of three services. Filament also removes a recurring admin-tooling cost you'd otherwise carry.

---

## 14. Risks

| Risk | Mitigation |
|---|---|
| **High RTO rate** | COD's structural weakness. OTP verification, address scoring, repeat-refuser blocklist, confirmation calls on high-value orders. RTO % is a first-class dashboard metric from day one |
| **Cash reconciliation gaps** | Automated remittance matching with a variance report. Never reconcile by hand in a spreadsheet |
| SEO drop after replatform | Complete 301 map, URL structure preserved where possible, structured data live at launch, Search Console monitored daily for 30 days |
| Courier account delays | Start applications in week 1 — this is the longest external lead time and it gates phase 4 |
| Service layer degrading into fat services | Architecture tests in CI, enforced from phase 0. Not a code-review convention |
| Octane state leakage between requests | A known Laravel footgun. Strict rules on static and singleton state, plus a dedicated test suite section for it |
| Staff can't operate the new admin | Filament ships in phase 1, not last. Two-week parallel run, recorded training videos, written runbooks |
| Data loss during migration | Migrate to staging first, reconciliation counts on every entity, keep the WooCommerce instance running for 30 days post-launch |
| Solo-developer bus factor | Everything in git, ADRs for every significant decision, infra as code, no undocumented manual server steps |

---

## 15. Immediate next steps

1. **Open courier merchant accounts today** — PostEx, Leopards, TCS. Longest lead time, gates phase 4, and their COD remittance cycles directly affect your cash flow.
2. Lock feature scope against section 6; push anything non-essential to a v2 backlog.
3. Export the current WooCommerce catalog to CSV so the real data shape is known before phase 1.
4. Decide hosting (managed Postgres provider, app server region) and provision a staging environment.
5. **Phase 0 can start immediately** — repo, CI, quality gates and the domain skeleton don't depend on any of the above.

---

*Versions verified August 2026: Laravel 13.25 (released Mar 2026, security support to Mar 2028), Inertia.js v2, Filament v4, PHP 8.4, PostgreSQL 17.*
