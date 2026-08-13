<?php

declare(strict_types=1);

namespace App\Domain\Delivery\DataObjects;

use DateTimeImmutable;

final readonly class BookingResult
{
    public function __construct(
        public string $consignmentNumber,
        public string $courier,
        public ?string $labelUrl = null,
        public ?DateTimeImmutable $estimatedDelivery = null,
    ) {}
}
