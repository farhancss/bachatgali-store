<?php

declare(strict_types=1);

use App\Domain\Inventory\Exceptions\StockUnavailable;
use App\Domain\Ordering\Exceptions\CodLimitExceeded;
use App\Domain\Shared\ValueObjects\Money;

/*
| These messages reach the ops queue and the customer-facing error log, so
| the numbers in them are asserted rather than assumed. A transposed
| requested/available pair reads as a plausible sentence and would otherwise
| go unnoticed.
*/

it('names the SKU, the shortfall and the request in a stock failure', function (): void {
    $exception = StockUnavailable::forSku('TSH-BLK-M', requested: 5, available: 2);

    expect($exception)->toBeInstanceOf(DomainException::class)
        ->and($exception->getMessage())->toBe('Only 2 of SKU TSH-BLK-M available, 5 requested.');
});

it('reports a sold-out SKU as zero available', function (): void {
    expect(StockUnavailable::forSku('MUG-WHT', requested: 1, available: 0)->getMessage())
        ->toBe('Only 0 of SKU MUG-WHT available, 1 requested.');
});

it('records the phone and the amount when COD is refused on risk', function (): void {
    $exception = CodLimitExceeded::for('+923001234567', Money::fromRupees(12_500));

    expect($exception)->toBeInstanceOf(DomainException::class)
        ->and($exception->getMessage())
        ->toBe('COD order of Rs. 12,500 refused for +923001234567 — risk band is blocked.');
});

it('states both the order value and the ceiling when over the limit', function (): void {
    $exception = CodLimitExceeded::overLimit(
        Money::fromRupees(60_000),
        Money::fromRupees(50_000),
    );

    expect($exception->getMessage())
        ->toBe('COD order of Rs. 60,000 exceeds the limit of Rs. 50,000.');
});

it('is throwable and catchable as a domain exception', function (): void {
    expect(fn () => throw CodLimitExceeded::overLimit(Money::fromRupees(1), Money::zero()))
        ->toThrow(DomainException::class);
});
