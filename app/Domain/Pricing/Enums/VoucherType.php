<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Enums;

/**
 * What a voucher actually does to a cart.
 *
 * Deliberately a closed set: "percentage off, capped" covers the vast
 * majority of real campaigns, and every additional type is another branch in
 * the money path that has to be tested to the same standard.
 */
enum VoucherType: string
{
    case PercentageOff = 'percentage_off';
    case FixedAmountOff = 'fixed_amount_off';
    case FreeDelivery = 'free_delivery';

    public function label(): string
    {
        return match ($this) {
            self::PercentageOff => 'Percentage off',
            self::FixedAmountOff => 'Fixed amount off',
            self::FreeDelivery => 'Free delivery',
        };
    }

    /** Does this discount the goods, as opposed to the delivery fee? */
    public function discountsGoods(): bool
    {
        return $this !== self::FreeDelivery;
    }

    public function needsPercentage(): bool
    {
        return $this === self::PercentageOff;
    }

    public function needsAmount(): bool
    {
        return $this === self::FixedAmountOff;
    }
}
