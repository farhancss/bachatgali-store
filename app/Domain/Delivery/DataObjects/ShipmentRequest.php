<?php

declare(strict_types=1);

namespace App\Domain\Delivery\DataObjects;

use App\Domain\Shared\Enums\City;
use App\Domain\Shared\ValueObjects\Money;

final readonly class ShipmentRequest
{
    /** @param array<int, array{sku: string, name: string, quantity: int}> $items */
    public function __construct(
        public string $orderReference,
        public string $recipientName,
        public string $recipientPhone,
        public City $city,
        public string $address,
        public Money $codAmount,
        public int $weightGrams,
        public array $items,
        public ?string $landmark = null,
        public ?string $instructions = null,
    ) {}

    public function isCashOnDelivery(): bool
    {
        return ! $this->codAmount->isZero();
    }

    public function itemCount(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }
}
