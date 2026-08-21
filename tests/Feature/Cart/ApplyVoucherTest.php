<?php

declare(strict_types=1);

use App\Domain\Pricing\Actions\ApplyVoucher;
use App\Domain\Pricing\DataObjects\VoucherResult;
use App\Domain\Pricing\Enums\VoucherRejection;
use App\Domain\Pricing\Enums\VoucherType;
use App\Domain\Pricing\Models\Voucher;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Carbon;

/*
| "Now" is injected rather than read from the clock, because the cases that
| cost money are exactly the boundary ones — the second a voucher expires.
*/

function at(string $when): DateTimeImmutable
{
    return new DateTimeImmutable($when);
}

function applyTo(?Voucher $voucher, int $subtotalRupees = 1_000, string $when = '2026-06-15 12:00:00'): VoucherResult
{
    return new ApplyVoucher()->handle($voucher, Money::fromRupees($subtotalRupees), at($when));
}

it('rejects an unknown code', function (): void {
    $result = applyTo(null);

    expect($result->applied)->toBeFalse()
        ->and($result->rejection)->toBe(VoucherRejection::NotFound)
        ->and($result->message())->toBe('That code is not recognised.');
});

it('takes a percentage off the subtotal', function (): void {
    $voucher = Voucher::factory()->percentage(10)->create(['code' => 'SAVE10']);

    $result = applyTo($voucher, 2_000);

    expect($result->applied)->toBeTrue()
        ->and($result->discount)->toBeMoney(20_000)
        ->and($result->code)->toBe('SAVE10');
});

it('caps a percentage discount at its ceiling', function (): void {
    // "20% off, up to Rs. 500" on a Rs. 10,000 cart is Rs. 500, not Rs. 2,000.
    $voucher = Voucher::factory()->percentage(20, capRupees: 500)->create();

    expect(applyTo($voucher, 10_000)->discount)->toBeMoney(50_000);
});

it('leaves a capped percentage alone when it is under the ceiling', function (): void {
    $voucher = Voucher::factory()->percentage(20, capRupees: 500)->create();

    expect(applyTo($voucher, 1_000)->discount)->toBeMoney(20_000);
});

it('never discounts more than the cart is worth', function (): void {
    $voucher = Voucher::factory()->fixed(5_000)->create();

    expect(applyTo($voucher, 800)->discount)->toBeMoney(80_000);
});

it('applies free delivery without discounting goods', function (): void {
    $result = applyTo(Voucher::factory()->freeDelivery()->create(['code' => 'SHIPFREE']));

    expect($result->applied)->toBeTrue()
        ->and($result->freeDelivery)->toBeTrue()
        ->and($result->discount)->toBeMoney(0);
});

it('treats expiry as exclusive', function (string $when, bool $applies): void {
    $voucher = Voucher::factory()->create(['expires_at' => '2026-06-15 00:00:00']);

    expect(applyTo($voucher, 1_000, $when)->applied)->toBe($applies);
})->with([
    'a second before midnight' => ['2026-06-14 23:59:59', true],
    'exactly at midnight' => ['2026-06-15 00:00:00', false],
    'after midnight' => ['2026-06-15 00:00:01', false],
]);

it('respects a start date', function (string $when, bool $applies): void {
    $voucher = Voucher::factory()->create(['starts_at' => '2026-06-15 09:00:00']);

    expect(applyTo($voucher, 1_000, $when)->applied)->toBe($applies);
})->with([
    'before it opens' => ['2026-06-15 08:59:59', false],
    'exactly on time' => ['2026-06-15 09:00:00', true],
    'after' => ['2026-06-15 09:00:01', true],
]);

it('rejects an inactive or exhausted voucher with the right reason', function (string $state, VoucherRejection $reason): void {
    $voucher = match ($state) {
        'inactive' => Voucher::factory()->create(['is_active' => false]),
        'exhausted' => Voucher::factory()->exhausted()->create(),
    };

    expect(applyTo($voucher)->rejection)->toBe($reason);
})->with([
    'inactive' => ['inactive', VoucherRejection::Inactive],
    'exhausted' => ['exhausted', VoucherRejection::UsageLimitReached],
]);

it('enforces minimum spend and says the shortfall is recoverable', function (): void {
    // Telling someone "invalid code" when they are Rs. 200 short sends them
    // to support instead of back to the cart.
    $voucher = Voucher::factory()->minimumSpend(2_000)->create();

    $result = applyTo($voucher, 1_800);

    expect($result->rejection)->toBe(VoucherRejection::BelowMinimumSpend)
        ->and($result->rejection?->isRecoverable())->toBeTrue()
        ->and(applyTo($voucher, 2_000)->applied)->toBeTrue();
});

it('refuses to discount an empty cart', function (): void {
    expect(applyTo(Voucher::factory()->create(), 0)->rejection)
        ->toBe(VoucherRejection::NothingToDiscount);
});

it('matches a code case-insensitively', function (): void {
    Voucher::factory()->create(['code' => 'SAVE10']);

    expect(Voucher::query()->forCode('save10')->first())->not->toBeNull()
        ->and(Voucher::query()->forCode('  Save10 ')->first())->not->toBeNull()
        ->and(Voucher::query()->forCode('nope')->first())->toBeNull();
});

it('reads every money and typed column back as its value object', function (): void {
    // Pins the cast map. A dropped cast turns paisa into a raw int in the
    // money path, which is silent until a total is a hundred times wrong —
    // mutation testing flagged exactly this gap.
    Voucher::factory()->create([
        'code' => 'TYPED',
        'type' => VoucherType::FixedAmountOff,
        'percentage' => null,
        'amount' => Money::fromRupees(300),
        'maximum_discount' => Money::fromRupees(500),
        'minimum_spend' => Money::fromRupees(1_000),
        'usage_limit' => 25,
        'starts_at' => '2026-01-01 00:00:00',
        'expires_at' => '2026-12-31 00:00:00',
    ]);

    $voucher = Voucher::query()->forCode('TYPED')->firstOrFail();

    expect($voucher->type)->toBe(VoucherType::FixedAmountOff)
        ->and($voucher->amount)->toBeMoney(30_000)
        ->and($voucher->maximum_discount)->toBeMoney(50_000)
        ->and($voucher->minimum_spend)->toBeMoney(100_000)
        ->and($voucher->is_active)->toBeTrue()
        ->and($voucher->usage_limit)->toBe(25)
        ->and($voucher->times_used)->toBe(0)
        ->and($voucher->starts_at)->toBeInstanceOf(Carbon::class)
        ->and($voucher->expires_at)->toBeInstanceOf(Carbon::class);
});

it('counts remaining uses against the limit', function (int $used, ?int $limit, bool $hasLeft): void {
    $voucher = Voucher::factory()->create(['times_used' => $used, 'usage_limit' => $limit]);

    expect($voucher->hasUsesLeft())->toBe($hasLeft);
})->with([
    'unlimited' => [999, null, true],
    'under the limit' => [4, 5, true],
    'exactly at the limit' => [5, 5, false],
    'over the limit' => [6, 5, false],
]);

it('discounts a percentage voucher off the subtotal it is given', function (int $percent, int $subtotal, int $expected): void {
    $voucher = Voucher::factory()->percentage($percent)->create();

    expect(applyTo($voucher, $subtotal)->discount)->toBeMoney($expected);
})->with([
    '10% of 1,000' => [10, 1_000, 10_000],
    '25% of 4,000' => [25, 4_000, 100_000],
    '100% of 500' => [100, 500, 50_000],
    '1% of 999' => [1, 999, 999],
]);
