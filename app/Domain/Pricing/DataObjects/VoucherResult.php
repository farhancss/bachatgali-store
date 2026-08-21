<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DataObjects;

use App\Domain\Pricing\Enums\VoucherRejection;
use App\Domain\Shared\ValueObjects\Money;

/**
 * The outcome of trying a voucher: either a discount, or a reason it did not
 * apply. Never both, never neither.
 */
final readonly class VoucherResult
{
    private function __construct(
        public bool $applied,
        public Money $discount,
        public bool $freeDelivery,
        public ?VoucherRejection $rejection,
        public ?string $code,
    ) {}

    public static function discount(Money $amount, string $code): self
    {
        return new self(true, $amount, false, null, $code);
    }

    public static function freeDelivery(string $code): self
    {
        return new self(true, Money::zero(), true, null, $code);
    }

    public static function rejected(VoucherRejection $reason): self
    {
        return new self(false, Money::zero(), false, $reason, null);
    }

    /** What to show the customer when it did not work. */
    public function message(): ?string
    {
        return $this->rejection?->message();
    }
}
