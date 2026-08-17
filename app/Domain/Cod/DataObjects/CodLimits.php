<?php

declare(strict_types=1);

namespace App\Domain\Cod\DataObjects;

use App\Domain\Shared\ValueObjects\Money;

/**
 * Order-value ceilings for cash on delivery. New customers are capped lower;
 * the ceiling rises as a delivery history builds.
 */
final readonly class CodLimits
{
    public function __construct(
        public Money $maxOrderValue,
        public Money $maxOrderValueNewCustomer,
    ) {}

    public function ceilingFor(bool $isFirstTimeCustomer): Money
    {
        return $isFirstTimeCustomer
            ? $this->maxOrderValueNewCustomer
            : $this->maxOrderValue;
    }

    /** "High value" begins at half the applicable ceiling. */
    public function highValueThresholdFor(bool $isFirstTimeCustomer): Money
    {
        return Money::fromPaisa(
            intdiv($this->ceilingFor($isFirstTimeCustomer)->paisa, 2),
        );
    }
}
