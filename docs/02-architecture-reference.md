# Architecture Reference — real code, not pseudocode
### What phase 0 and phase 3 actually produce

This is the working shape of the service layer, its tests, and the quality gates. Every snippet here is code I'd write on day one, not illustration.

---

## 1. A DTO at the boundary

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DataObjects;

use App\Domain\Delivery\Enums\City;
use Spatie\LaravelData\Attributes\Validation\{Max, Regex, Required};
use Spatie\LaravelData\Data;

final class CheckoutData extends Data
{
    public function __construct(
        #[Required, Max(120)]
        public readonly string $fullName,

        #[Required, Regex('/^03\d{2}\s?\d{7}$/')]
        public readonly string $phone,

        public readonly City $city,

        #[Required, Max(500)]
        public readonly string $address,

        public readonly ?string $landmark,
        public readonly ?string $voucherCode,
        public readonly bool $whatsappUpdates = true,
    ) {}

    /** Normalised to a single comparable form for blocklists and duplicate detection. */
    public function normalisedPhone(): string
    {
        return preg_replace('/\D/', '', $this->phone);
    }
}
```

One definition validates the request, types the Action, and — via `spatie/typescript-transformer` — generates the matching TypeScript interface for the Vue checkout page. The form and the backend can't drift.

---

## 2. The Action — one job, fully testable

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Cart\Cart;
use App\Domain\Cod\Actions\ScoreCodRisk;
use App\Domain\Cod\Enums\RiskBand;
use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\ReserveStock;
use App\Domain\Ordering\DataObjects\CheckoutData;
use App\Domain\Ordering\Events\OrderPlaced;
use App\Domain\Ordering\Exceptions\{CodLimitExceeded, StockUnavailable};
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\States\{AwaitingConfirmation, Pending};
use App\Domain\Pricing\Actions\CalculateOrderTotals;
use Illuminate\Support\Facades\DB;

final readonly class PlaceOrder
{
    public function __construct(
        private CalculateOrderTotals $totals,
        private ScoreCodRisk $risk,
        private ReserveStock $reserve,
    ) {}

    /**
     * @throws StockUnavailable|CodLimitExceeded
     */
    public function handle(CheckoutData $data, Cart $cart, ?Customer $customer = null): Order
    {
        $totals = $this->totals->handle($cart, $data->voucherCode, $data->city);
        $risk   = $this->risk->handle($data, $totals->grandTotal, $customer);

        if ($risk->band === RiskBand::Blocked) {
            throw CodLimitExceeded::for($data->normalisedPhone(), $totals->grandTotal);
        }

        $order = DB::transaction(function () use ($data, $cart, $totals, $risk, $customer): Order {
            // Row-level locks inside; throws StockUnavailable rather than overselling.
            $this->reserve->handle($cart->items());

            $order = Order::create([
                'customer_id'    => $customer?->id,
                'recipient_name' => $data->fullName,
                'phone'          => $data->normalisedPhone(),
                'city'           => $data->city,
                'address'        => $data->address,
                'landmark'       => $data->landmark,
                'subtotal'       => $totals->subtotal,        // int, paisa
                'discount'       => $totals->discount,
                'delivery_fee'   => $totals->deliveryFee,
                'grand_total'    => $totals->grandTotal,
                'cod_amount'     => $totals->grandTotal,
                'risk_band'      => $risk->band,
                'risk_factors'   => $risk->factors,
                'state'          => $risk->band === RiskBand::High
                    ? AwaitingConfirmation::class   // human calls before dispatch
                    : Pending::class,
            ]);

            $order->items()->createMany($cart->toOrderItems());  // price snapshots

            return $order;
        });

        // Side effects live in listeners: WhatsApp, SMS, courier booking,
        // search reindex, analytics. Adding one never touches this class.
        OrderPlaced::dispatch($order);

        return $order;
    }
}
```

Note what this class does **not** do: send notifications, book couriers, write to the search index, or format anything. Those are listeners. That's why it stays under 60 lines at month twelve.

---

## 3. The state machine — illegal transitions become impossible

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class OrderState extends State
{
    abstract public function label(): string;
    abstract public function isTerminal(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class,             Confirmed::class)
            ->allowTransition(Pending::class,             Cancelled::class)
            ->allowTransition(AwaitingConfirmation::class, Confirmed::class)
            ->allowTransition(AwaitingConfirmation::class, Cancelled::class)
            ->allowTransition(Confirmed::class,           Packed::class)
            ->allowTransition(Packed::class,              Shipped::class)
            ->allowTransition(Shipped::class,             OutForDelivery::class)
            ->allowTransition(OutForDelivery::class,      Delivered::class,  MarkDelivered::class)
            ->allowTransition(OutForDelivery::class,      Returned::class,   HandleRto::class)
            ->allowTransition(Delivered::class,           ReturnRequested::class)
            ->allowTransition(ReturnRequested::class,     Refunded::class);
    }
}
```

`MarkDelivered` is a transition class that also records the COD collection and releases the reservation — the state change and its financial consequence can't be separated.

---

## 4. External services behind a contract

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Courier\Contracts;

use App\Domain\Delivery\DataObjects\{BookingResult, ShipmentRequest, TrackingSnapshot};

interface CourierGateway
{
    public function book(ShipmentRequest $request): BookingResult;

    public function track(string $consignmentNumber): TrackingSnapshot;

    public function cancel(string $consignmentNumber): bool;

    /** @return array<int, string> Cities this courier actually serves. */
    public function servicableCities(): array;

    public function identifier(): string;
}
```

```php
final class FakeCourierGateway implements CourierGateway
{
    /** @var array<string, ShipmentRequest> */
    public array $booked = [];
    public bool $shouldFail = false;

    public function book(ShipmentRequest $request): BookingResult
    {
        if ($this->shouldFail) {
            throw new CourierUnavailable('Fake courier is down');
        }

        $cn = 'FAKE-'.str_pad((string) (count($this->booked) + 1), 8, '0', STR_PAD_LEFT);
        $this->booked[$cn] = $request;

        return new BookingResult($cn, labelUrl: "https://fake.test/label/{$cn}");
    }
    // …
}
```

Bound in a service provider; the test suite swaps the real gateway for the fake in one line. **No test ever calls a real courier API.**

---

## 5. Tests — what they actually look like

### Unit: the voucher engine

```php
it('caps a percentage voucher at its maximum discount', function () {
    $voucher = Voucher::factory()->percentage(20)->maxDiscount(50_000)->make();

    $result = app(ApplyVoucher::class)->handle($voucher, subtotal: 400_000);

    expect($result->discount)->toBe(50_000);   // 20% would be 80,000 — capped
});

it('rejects a voucher below its minimum spend', function () {
    $voucher = Voucher::factory()->fixed(30_000)->minSpend(250_000)->make();

    expect(fn () => app(ApplyVoucher::class)->handle($voucher, subtotal: 200_000))
        ->toThrow(MinimumSpendNotMet::class);
});

it('never produces a negative total', function (int $subtotal, int $voucherValue) {
    $voucher = Voucher::factory()->fixed($voucherValue)->make();

    $result = app(ApplyVoucher::class)->handle($voucher, $subtotal);

    expect($result->total())->toBeGreaterThanOrEqual(0);
})->with([
    [100_000, 150_000],
    [1, 999_999],
    [0, 50_000],
]);
```

### Feature: the checkout path that matters

```php
it('places a COD order and reserves stock', function () {
    $product = Product::factory()->inStock(10)->create();
    $cart    = CartFactory::withItems([[$product, 2]]);

    $response = $this->post(route('checkout.place'), [
        'fullName' => 'Ayesha Khan',
        'phone'    => '0300 1234567',
        'city'     => 'lahore',
        'address'  => '12 Street 4, DHA Phase 5',
    ]);

    $response->assertRedirectToRoute('orders.confirmation');

    $order = Order::sole();
    expect($order->state)->toBeInstanceOf(Pending::class)
        ->and($order->cod_amount)->toBe($order->grand_total)
        ->and($product->fresh()->available_stock)->toBe(8);

    Event::assertDispatched(OrderPlaced::class);
});

it('routes a high-risk order to manual confirmation instead of dispatch', function () {
    // Two prior refusals from this number.
    Order::factory()->count(2)->returned()->forPhone('03001234567')->create();

    $this->post(route('checkout.place'), validCheckoutPayload(phone: '0300 1234567'));

    expect(Order::latest()->sole())
        ->state->toBeInstanceOf(AwaitingConfirmation::class)
        ->risk_band->toBe(RiskBand::High);
});

it('does not oversell when two customers check out simultaneously', function () {
    $product = Product::factory()->inStock(1)->create();

    $results = concurrently(2, fn () => placeOrderFor($product, qty: 1));

    expect($results)->toHaveCount(2)
        ->and(collect($results)->filter(fn ($r) => $r instanceof Order))->toHaveCount(1)
        ->and(collect($results)->filter(fn ($r) => $r instanceof StockUnavailable))->toHaveCount(1);
});
```

### Integration: remittance reconciliation, the highest-stakes code

```php
it('flags a variance when the courier remits less than the COD amount', function () {
    $order = Order::factory()->delivered()->codAmount(1_019_700)->withCn('PX-1001')->create();

    $report = app(ReconcileRemittance::class)->handle(
        RemittanceFile::fromFixture('postex/partial-batch.csv')
    );

    expect($report->matched)->toHaveCount(0)
        ->and($report->variances)->toHaveCount(1)
        ->and($report->variances[0])
            ->consignment->toBe('PX-1001')
            ->expected->toBe(1_019_700)
            ->received->toBe(1_000_000)
            ->reason->toBe(VarianceReason::ShortRemittance);

    expect($order->fresh()->codCollection->status)->toBe(RemittanceStatus::Disputed);
});
```

Fixtures are captured from real courier sandbox responses, including the ugly ones: duplicate CNs, returned-but-marked-delivered, and batches that arrive out of order.

---

## 6. The quality gate config

**`phpstan.neon`**
```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    level: 8
    paths: [app, database, routes, tests]
    checkMissingIterableValueType: true
    treatPhpDocTypesAsCertain: false
```
```neon
# phpstan-domain.neon — stricter rules for the code that handles money
parameters:
    level: 9
    paths: [app/Domain]
    checkUninitializedProperties: true
```

**`composer.json` scripts** — the same commands run locally and in CI:
```json
{
  "scripts": {
    "lint":      "pint --test",
    "fix":       "pint",
    "types":     "phpstan analyse --memory-limit=1G",
    "types:domain": "phpstan analyse -c phpstan-domain.neon",
    "refactor":  "rector --dry-run",
    "test":      "pest --parallel",
    "test:cov":  "pest --parallel --coverage --min=85",
    "test:type": "pest --type-coverage --min=95",
    "test:arch": "pest --group=arch",
    "test:mutate": "pest --mutate --covered-only --min=75 --path=app/Domain/Pricing,app/Domain/Cod",
    "check":     ["@lint", "@types", "@types:domain", "@refactor", "@test:cov", "@test:type"]
  }
}
```

`composer check` before you push. If it's green locally, CI will be green.

---

## 7. What phase 0 hands you

A repository where:

- `composer check` runs the full gate suite and passes
- `./vendor/bin/pest` runs an empty-but-real test suite including architecture tests that already enforce the layering
- Docker compose brings up PHP 8.4 + Octane, PostgreSQL 17, Redis, Typesense and Mailpit with one command
- CI is wired end-to-end and deploys to staging on merge
- The domain folder skeleton exists with one worked vertical slice (a product, its DTO, an Action, its tests) as the pattern everything else copies
- Tailwind tokens from the prototype are in place, dark/light working, Vue + Inertia + TypeScript building through Vite

That's the point where adding a feature is mechanical rather than architectural — which is the whole reason to spend a session on it before writing any business logic.
