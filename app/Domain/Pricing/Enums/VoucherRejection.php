<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Enums;

/**
 * Why a voucher did not apply.
 *
 * An enum rather than a thrown exception: a rejected voucher is an ordinary
 * outcome of checkout, not an exceptional one, and the customer needs to be
 * told which of these it was. "Invalid code" for an expired voucher sends
 * people to support instead of back to the cart.
 */
enum VoucherRejection: string
{
    case NotFound = 'not_found';
    case Inactive = 'inactive';
    case NotStarted = 'not_started';
    case Expired = 'expired';
    case UsageLimitReached = 'usage_limit_reached';
    case BelowMinimumSpend = 'below_minimum_spend';
    case NothingToDiscount = 'nothing_to_discount';

    public function message(): string
    {
        return match ($this) {
            self::NotFound => 'That code is not recognised.',
            self::Inactive => 'That code is no longer active.',
            self::NotStarted => 'That code is not available yet.',
            self::Expired => 'That code has expired.',
            self::UsageLimitReached => 'That code has been fully claimed.',
            self::BelowMinimumSpend => 'Your order is below the minimum for that code.',
            self::NothingToDiscount => 'There is nothing in your cart to discount.',
        };
    }

    /** Should the customer be nudged to add more rather than give up? */
    public function isRecoverable(): bool
    {
        return $this === self::BelowMinimumSpend;
    }
}
