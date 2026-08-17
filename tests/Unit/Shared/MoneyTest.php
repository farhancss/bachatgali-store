<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\Money;

it('constructs from paisa and rupees consistently', function (): void {
    expect(Money::fromRupees(2_500))->toBeMoney(250_000)
        ->and(Money::fromPaisa(250_000)->toRupees())->toBe('2,500');
});

it('adds and subtracts without drift', function (): void {
    $total = Money::fromRupees(4_299)
        ->plus(Money::fromRupees(990)->times(2))
        ->plus(Money::fromRupees(2_990));

    // 4,299 + (990 × 2) + 2,990 = Rs. 9,269
    expect($total)->toBeMoney(926_900);
});

it('clamps discounts so a total can never go negative', function (): void {
    $subtotal = Money::fromRupees(1_000);
    $discount = Money::fromRupees(1_500);

    expect($subtotal->minusClamped($discount))->toBeMoney(0);
});

it('calculates percentages with integer arithmetic, rounding half up', function (int $paisa, int $percent, int $expected): void {
    expect(Money::fromPaisa($paisa)->percentage($percent))->toBeMoney($expected);
})->with([
    'exact' => [100_000, 20, 20_000],
    'rounds half up' => [1_005, 50, 503],
    'zero percent' => [100_000, 0, 0],
    'full amount' => [100_000, 100, 100_000],
]);

it('rejects an out-of-range percentage', function (int $percent): void {
    expect(fn (): Money => Money::fromRupees(100)->percentage($percent))
        ->toThrow(InvalidArgumentException::class);
})->with([-1, 101, 999]);

it('rejects a negative multiplier', function (): void {
    expect(fn (): Money => Money::fromRupees(100)->times(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('serialises to integer paisa for the wire', function (): void {
    expect(json_encode(['total' => Money::fromRupees(4_299)]))
        ->toBe('{"total":429900}');
});
