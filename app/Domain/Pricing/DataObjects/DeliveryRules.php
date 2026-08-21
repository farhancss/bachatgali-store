<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DataObjects;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Delivery and COD tunables, injected rather than read from config inside the
 * calculator — which is what keeps the money path a pure unit test.
 */
final readonly class DeliveryRules
{
    public function __construct(
        public Money $freeDeliveryThreshold,
        public Money $standardFee,
        public Money $codHandlingFee,
    ) {}

    /** Free delivery begins AT the threshold, not above it. */
    public function feeFor(Money $subtotal): Money
    {
        return $subtotal->isGreaterThanOrEqualTo($this->freeDeliveryThreshold)
            ? Money::zero()
            : $this->standardFee;
    }

    public function qualifiesForFreeDelivery(Money $subtotal): bool
    {
        return $subtotal->isGreaterThanOrEqualTo($this->freeDeliveryThreshold);
    }

    /** How much more to spend to earn free delivery — drives the cart nudge. */
    public function shortfallToFreeDelivery(Money $subtotal): Money
    {
        return $this->freeDeliveryThreshold->minusClamped($subtotal);
    }
}
