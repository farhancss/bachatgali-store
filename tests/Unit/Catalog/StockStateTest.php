<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\StockState;

/*
| Availability is derived from quantity, so the boundaries are asserted
| exactly. Getting the low-stock edge wrong either cries wolf on every
| product or never warns at all.
*/

it('derives availability from quantity and threshold', function (int $qty, int $threshold, bool $backorder, StockState $expected): void {
    expect(StockState::forQuantity($qty, $threshold, $backorder))->toBe($expected);
})->with([
    'plenty' => [100, 5, false, StockState::InStock],
    'one above the threshold' => [6, 5, false, StockState::InStock],
    'exactly at the threshold' => [5, 5, false, StockState::LowStock],
    'one left' => [1, 5, false, StockState::LowStock],
    'none' => [0, 5, false, StockState::OutOfStock],
    'none but backorderable' => [0, 5, true, StockState::Backorder],
    'negative reads as out of stock' => [-3, 5, false, StockState::OutOfStock],
]);

it('never reports low stock when no threshold is set', function (): void {
    expect(StockState::forQuantity(1, lowStockThreshold: 0))->toBe(StockState::InStock)
        ->and(StockState::forQuantity(0, lowStockThreshold: 0))->toBe(StockState::OutOfStock);
});

it('blocks the cart only when genuinely out of stock', function (StockState $state, bool $purchasable): void {
    expect($state->isPurchasable())->toBe($purchasable);
})->with([
    'in stock' => [StockState::InStock, true],
    'low stock' => [StockState::LowStock, true],
    'backorder' => [StockState::Backorder, true],
    'pre-order' => [StockState::PreOrder, true],
    'out of stock' => [StockState::OutOfStock, false],
]);

it('separates purchasable from ships-immediately', function (): void {
    // A backorder is sellable but must not promise the standard window —
    // promising it is how a COD order becomes an RTO.
    expect(StockState::Backorder->isPurchasable())->toBeTrue()
        ->and(StockState::Backorder->shipsImmediately())->toBeFalse()
        ->and(StockState::PreOrder->shipsImmediately())->toBeFalse()
        ->and(StockState::InStock->shipsImmediately())->toBeTrue();
});

it('marks only low stock as scarce', function (): void {
    $scarce = array_values(array_filter(StockState::cases(), fn (StockState $s): bool => $s->isScarce()));

    expect($scarce)->toBe([StockState::LowStock]);
});

it('labels and colours every state', function (StockState $state): void {
    expect($state->label())->not->toBeEmpty()
        ->and($state->colour())->toBeIn(['success', 'warning', 'danger', 'info']);
})->with(StockState::cases());
