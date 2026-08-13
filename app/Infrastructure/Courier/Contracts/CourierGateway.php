<?php

declare(strict_types=1);

namespace App\Infrastructure\Courier\Contracts;

use App\Domain\Delivery\DataObjects\BookingResult;
use App\Domain\Delivery\DataObjects\ShipmentRequest;
use App\Domain\Delivery\DataObjects\TrackingSnapshot;
use App\Domain\Shared\Enums\City;

/**
 * Every courier integration implements this. The test suite runs entirely
 * against FakeCourierGateway — no test ever calls a live courier API.
 *
 * Adding a courier is a new class plus a config entry, nothing more.
 */
interface CourierGateway
{
    public function identifier(): string;

    public function book(ShipmentRequest $request): BookingResult;

    public function track(string $consignmentNumber): TrackingSnapshot;

    public function cancel(string $consignmentNumber): bool;

    /** @return array<int, City> */
    public function serviceableCities(): array;

    public function supportsCashOnDelivery(): bool;
}
