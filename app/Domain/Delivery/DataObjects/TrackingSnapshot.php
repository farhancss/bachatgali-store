<?php

declare(strict_types=1);

namespace App\Domain\Delivery\DataObjects;

use App\Domain\Delivery\Enums\ShipmentStatus;
use DateTimeImmutable;

final readonly class TrackingSnapshot
{
    /** @param array<int, array{status: ShipmentStatus, at: DateTimeImmutable, note: ?string}> $history */
    public function __construct(
        public string $consignmentNumber,
        public ShipmentStatus $status,
        public DateTimeImmutable $updatedAt,
        public array $history = [],
        public ?string $riderName = null,
        public ?string $failureReason = null,
    ) {}
}
