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

it('starts at zero and knows it', function (): void {
    expect(Money::zero())->toBeMoney(0)
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromPaisa(1)->isZero())->toBeFalse();
});

it('subtracts without clamping, so a refund can go negative on purpose', function (): void {
    // minus() is the raw operation; minusClamped() is the one checkout uses.
    expect(Money::fromRupees(1_000)->minus(Money::fromRupees(250)))->toBeMoney(75_000)
        ->and(Money::fromRupees(100)->minus(Money::fromRupees(150)))->toBeMoney(-5_000);
});

it('compares amounts', function (): void {
    $small = Money::fromRupees(100);
    $large = Money::fromRupees(250);

    expect($large->isGreaterThan($small))->toBeTrue()
        ->and($small->isGreaterThan($large))->toBeFalse()
        ->and($small->isGreaterThan($small))->toBeFalse()
        ->and($small->isGreaterThanOrEqualTo($small))->toBeTrue()
        ->and($small->isGreaterThanOrEqualTo($large))->toBeFalse();
});

it('is equal by value, not by identity', function (): void {
    expect(Money::fromRupees(2_500)->equals(Money::fromPaisa(250_000)))->toBeTrue()
        ->and(Money::fromRupees(2_500)->equals(Money::fromRupees(2_501)))->toBeFalse();
});

it('formats whole rupees with a thousands separator', function (int $paisa, string $expected): void {
    expect(Money::fromPaisa($paisa)->format())->toBe($expected);
})->with([
    'zero' => [0, 'Rs. 0'],
    'sub-rupee truncates' => [99, 'Rs. 0'],
    'thousands' => [250_000, 'Rs. 2,500'],
    'millions' => [5_000_000, 'Rs. 50,000'],
]);

it('takes a currency symbol for other storefronts', function (): void {
    expect(Money::fromRupees(1_200)->format('PKR'))->toBe('PKR 1,200');
});

it('stringifies to the default formatting for Blade interpolation', function (): void {
    $total = Money::fromRupees(4_299);

    expect((string) $total)->toBe('Rs. 4,299')
        ->and("Total: {$total}")->toBe('Total: Rs. 4,299')
        ->and($total)->toBeInstanceOf(Stringable::class);
});
