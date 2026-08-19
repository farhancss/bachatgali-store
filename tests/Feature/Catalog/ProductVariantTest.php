<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\StockState;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Shared\ValueObjects\Money;

/*
| This is the first place MoneyCast meets a real database, so the round trip
| is asserted rather than assumed — including that the column really holds
| integer paisa and not a decimal the driver rounded on the way in.
*/

it('round-trips money through the database as integer paisa', function (): void {
    $variant = ProductVariant::factory()->priced(4_299)->create();

    expect($variant->refresh()->price)->toBeMoney(429_900);

    $raw = DB::table('product_variants')->where('id', $variant->id)->value('price');

    expect((int) $raw)->toBe(429_900);
});

it('keeps nullable money columns null rather than zero', function (): void {
    $variant = ProductVariant::factory()->create();

    expect($variant->refresh()->compare_at_price)->toBeNull()
        ->and($variant->cost)->toBeNull();
});

it('derives stock state from its own columns', function (string $state, StockState $expected): void {
    $variant = ProductVariant::factory()->{$state}()->create();

    expect($variant->refresh()->stockState())->toBe($expected);
})->with([
    'out of stock' => ['outOfStock', StockState::OutOfStock],
    'low stock' => ['lowStock', StockState::LowStock],
    'backorderable' => ['backorderable', StockState::Backorder],
    'pre-order' => ['preOrder', StockState::PreOrder],
]);

it('lets pre-order override a healthy quantity', function (): void {
    // Pre-order is a merchandising decision, not a fact about the warehouse.
    $variant = ProductVariant::factory()->preOrder()->create(['stock_quantity' => 500]);

    expect($variant->stockState())->toBe(StockState::PreOrder);
});

it('recognises a genuine reduction as a sale', function (): void {
    $variant = ProductVariant::factory()->onSale(fromRupees: 5_000, toRupees: 3_500)->create();

    expect($variant->isOnSale())->toBeTrue()
        ->and($variant->savings())->toBeMoney(150_000);
});

it('refuses to call an inflated compare-at price a sale', function (): void {
    $variant = ProductVariant::factory()->create([
        'price' => Money::fromRupees(3_000),
        'compare_at_price' => Money::fromRupees(2_000),
    ]);

    expect($variant->isOnSale())->toBeFalse()
        ->and($variant->savings())->toBeMoney(0);
});

it('reports no savings when there is no compare-at price', function (): void {
    expect(ProductVariant::factory()->create()->savings())->toBeMoney(0);
});

it('scopes to variants with stock on hand', function (): void {
    $inStock = ProductVariant::factory()->create(['stock_quantity' => 10]);
    $none = ProductVariant::factory()->outOfStock()->create();

    expect(ProductVariant::query()->inStock()->pluck('id'))
        ->toContain($inStock->id)
        ->not->toContain($none->id);
});
