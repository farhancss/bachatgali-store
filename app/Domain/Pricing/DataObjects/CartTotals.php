<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DataObjects;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Every number the cart and checkout summary show, computed once.
 *
 * Totals are a value object rather than a bag of loose figures so a view can
 * never render a subtotal from one calculation beside a total from another.
 */
final readonly class CartTotals
{
    public function __construct(
        public Money $subtotal,
        public Money $discount,
        public Money $deliveryFee,
        public Money $codHandlingFee,
        public Money $total,
        public int $itemCount,
        public bool $freeDeliveryApplied = false,
        public ?string $appliedVoucherCode = null,
    ) {}

    public static function empty(): self
    {
        return new self(
            subtotal: Money::zero(),
            discount: Money::zero(),
            deliveryFee: Money::zero(),
            codHandlingFee: Money::zero(),
            total: Money::zero(),
            itemCount: 0,
        );
    }

    public function isEmpty(): bool
    {
        return $this->itemCount === 0;
    }

    public function hasDiscount(): bool
    {
        return ! $this->discount->isZero();
    }

    /** What the customer hands the rider. */
    public function amountDueOnDelivery(): Money
    {
        return $this->total;
    }
}
