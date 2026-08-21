<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\DataObjects\VoucherResult;
use App\Domain\Pricing\Enums\VoucherRejection;
use App\Domain\Pricing\Enums\VoucherType;
use App\Domain\Pricing\Models\Voucher;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

/**
 * Decides whether a voucher applies, and for how much.
 *
 * The voucher is passed in rather than looked up here, and "now" is passed in
 * rather than read from the clock. Both are what make this a pure unit test:
 * expiry-boundary cases are the ones that actually cost money, and they are
 * untestable against a real clock.
 *
 * Rejections are returned, not thrown. A rejected voucher is an ordinary
 * outcome of checkout and the customer needs to know which reason it was.
 */
final readonly class ApplyVoucher
{
    public function handle(?Voucher $voucher, Money $subtotal, DateTimeImmutable $now): VoucherResult
    {
        if (! $voucher instanceof Voucher) {
            return VoucherResult::rejected(VoucherRejection::NotFound);
        }

        if (! $voucher->is_active) {
            return VoucherResult::rejected(VoucherRejection::Inactive);
        }

        if ($voucher->starts_at !== null && $now < $voucher->starts_at->toDateTimeImmutable()) {
            return VoucherResult::rejected(VoucherRejection::NotStarted);
        }

        // Expiry is exclusive: a voucher valid "until midnight" stops working
        // AT midnight, not a second after.
        if ($voucher->expires_at !== null && $now >= $voucher->expires_at->toDateTimeImmutable()) {
            return VoucherResult::rejected(VoucherRejection::Expired);
        }

        if (! $voucher->hasUsesLeft()) {
            return VoucherResult::rejected(VoucherRejection::UsageLimitReached);
        }

        if ($subtotal->isZero()) {
            return VoucherResult::rejected(VoucherRejection::NothingToDiscount);
        }

        if ($subtotal->paisa < $voucher->minimum_spend->paisa) {
            return VoucherResult::rejected(VoucherRejection::BelowMinimumSpend);
        }

        return match ($voucher->type) {
            VoucherType::FreeDelivery => VoucherResult::freeDelivery($voucher->code),
            VoucherType::PercentageOff => VoucherResult::discount(
                $this->cap($subtotal->percentage($voucher->percentage ?? 0), $voucher),
                $voucher->code,
            ),
            VoucherType::FixedAmountOff => VoucherResult::discount(
                // Never discount more than the cart is worth.
                $this->smaller($voucher->amount ?? Money::zero(), $subtotal),
                $voucher->code,
            ),
        };
    }

    /** Percentage vouchers usually carry a ceiling: "20% off, up to Rs. 500". */
    private function cap(Money $discount, Voucher $voucher): Money
    {
        $ceiling = $voucher->maximum_discount;

        return $ceiling instanceof Money ? $this->smaller($discount, $ceiling) : $discount;
    }

    private function smaller(Money $a, Money $b): Money
    {
        return $a->isGreaterThan($b) ? $b : $a;
    }
}
