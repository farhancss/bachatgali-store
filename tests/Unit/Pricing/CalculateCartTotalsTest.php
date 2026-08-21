<?php

declare(strict_types=1);

use App\Domain\Pricing\Actions\CalculateCartTotals;
use App\Domain\Pricing\DataObjects\DeliveryRules;
use App\Domain\Pricing\DataObjects\VoucherResult;
use App\Domain\Pricing\Enums\VoucherRejection;
use App\Domain\Shared\ValueObjects\Money;

/*
| The money path. Table-driven so that retuning a threshold tells you exactly
| which carts changed total, and pure — no container, no database, no clock.
*/

function rules(int $freeFrom = 2_500, int $fee = 250, int $codFee = 0): DeliveryRules
{
    return new DeliveryRules(
        freeDeliveryThreshold: Money::fromRupees($freeFrom),
        standardFee: Money::fromRupees($fee),
        codHandlingFee: Money::fromRupees($codFee),
    );
}

/** @return list<array{unit_price: Money, quantity: int}> */
function lines(array ...$pairs): array
{
    return array_map(
        static fn (array $p): array => ['unit_price' => Money::fromRupees($p[0]), 'quantity' => $p[1]],
        $pairs,
    );
}

it('totals an empty cart to nothing', function (): void {
    $totals = new CalculateCartTotals(rules())->handle([]);

    expect($totals->isEmpty())->toBeTrue()
        ->and($totals->total)->toBeMoney(0)
        ->and($totals->deliveryFee)->toBeMoney(0)
        ->and($totals->itemCount)->toBe(0);
});

it('sums line totals by quantity', function (): void {
    $totals = new CalculateCartTotals(rules())->handle(lines([1_000, 2], [500, 3]));

    expect($totals->subtotal)->toBeMoney(350_000)
        ->and($totals->itemCount)->toBe(5);
});

it('charges delivery below the threshold and not at or above it', function (int $subtotal, int $expectedFee): void {
    $totals = new CalculateCartTotals(rules(freeFrom: 2_500, fee: 250))->handle(lines([$subtotal, 1]));

    expect($totals->deliveryFee)->toBeMoney($expectedFee * 100);
})->with([
    'well below' => [500, 250],
    'just below' => [2_499, 250],
    'exactly at the threshold' => [2_500, 0],
    'above' => [5_000, 0],
]);

it('assesses delivery on the discounted subtotal, not the original', function (): void {
    // The expensive mistake: a Rs. 600 discount drops a Rs. 2,600 order to
    // Rs. 2,000, which no longer earns free delivery. Charging on the
    // pre-discount figure gives away the fee on every such order.
    $voucher = VoucherResult::discount(Money::fromRupees(600), 'SAVE600');

    $totals = new CalculateCartTotals(rules(freeFrom: 2_500, fee: 250))
        ->handle(lines([2_600, 1]), $voucher);

    expect($totals->subtotal)->toBeMoney(260_000)
        ->and($totals->discount)->toBeMoney(60_000)
        ->and($totals->deliveryFee)->toBeMoney(25_000)
        ->and($totals->freeDeliveryApplied)->toBeFalse()
        ->and($totals->total)->toBeMoney(225_000);
});

it('never discounts below zero', function (): void {
    $voucher = VoucherResult::discount(Money::fromRupees(5_000), 'TOOBIG');

    $totals = new CalculateCartTotals(rules(fee: 250))->handle(lines([1_000, 1]), $voucher);

    expect($totals->discount)->toBeMoney(100_000)   // clamped to the subtotal
        ->and($totals->total)->toBeMoney(25_000)    // delivery only
        ->and($totals->total->paisa)->toBeGreaterThanOrEqual(0);
});

it('waives delivery for a free-delivery voucher regardless of subtotal', function (): void {
    $totals = new CalculateCartTotals(rules(freeFrom: 2_500, fee: 250))
        ->handle(lines([300, 1]), VoucherResult::freeDelivery('FREESHIP'));

    expect($totals->deliveryFee)->toBeMoney(0)
        ->and($totals->discount)->toBeMoney(0)
        ->and($totals->total)->toBeMoney(30_000)
        ->and($totals->appliedVoucherCode)->toBe('FREESHIP');
});

it('ignores a rejected voucher entirely', function (): void {
    $rejected = VoucherResult::rejected(VoucherRejection::Expired);

    $totals = new CalculateCartTotals(rules(fee: 250))->handle(lines([1_000, 1]), $rejected);

    expect($totals->discount)->toBeMoney(0)
        ->and($totals->appliedVoucherCode)->toBeNull()
        ->and($totals->hasDiscount())->toBeFalse();
});

it('adds the COD handling fee on top of everything', function (): void {
    $totals = new CalculateCartTotals(rules(freeFrom: 2_500, fee: 250, codFee: 50))
        ->handle(lines([1_000, 1]));

    // 1,000 goods + 250 delivery + 50 handling
    expect($totals->codHandlingFee)->toBeMoney(5_000)
        ->and($totals->total)->toBeMoney(130_000)
        ->and($totals->amountDueOnDelivery())->toBeMoney(130_000);
});

it('composes discount, delivery and COD fee in the right order', function (): void {
    $totals = new CalculateCartTotals(rules(freeFrom: 5_000, fee: 200, codFee: 100))
        ->handle(lines([3_000, 2]), VoucherResult::discount(Money::fromRupees(1_500), 'X'));

    // 6,000 − 1,500 = 4,500 → under 5,000 so delivery applies → +200 +100
    expect($totals->subtotal)->toBeMoney(600_000)
        ->and($totals->discount)->toBeMoney(150_000)
        ->and($totals->deliveryFee)->toBeMoney(20_000)
        ->and($totals->total)->toBeMoney(480_000);
});

it('reports the shortfall to free delivery for the cart nudge', function (): void {
    $r = rules(freeFrom: 2_500);

    expect($r->shortfallToFreeDelivery(Money::fromRupees(1_800)))->toBeMoney(70_000)
        ->and($r->shortfallToFreeDelivery(Money::fromRupees(3_000)))->toBeMoney(0)
        ->and($r->qualifiesForFreeDelivery(Money::fromRupees(2_500)))->toBeTrue();
});
