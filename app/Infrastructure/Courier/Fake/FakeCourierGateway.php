<?php

declare(strict_types=1);

namespace App\Infrastructure\Courier\Fake;

use App\Domain\Delivery\DataObjects\BookingResult;
use App\Domain\Delivery\DataObjects\ShipmentRequest;
use App\Domain\Delivery\DataObjects\TrackingSnapshot;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Shared\Enums\City;
use App\Infrastructure\Courier\Contracts\CourierGateway;
use DateTimeImmutable;
use RuntimeException;

/**
 * In-memory courier used by the entire test suite and by local development.
 * Set COURIER_DEFAULT=fake to run the app without any courier credentials.
 */
final class FakeCourierGateway implements CourierGateway
{
    /** @var array<string, ShipmentRequest> */
    public array $booked = [];

    /** @var array<string, ShipmentStatus> */
    public array $statuses = [];

    public bool $shouldFail = false;

    public function identifier(): string
    {
        return 'fake';
    }

    public function book(ShipmentRequest $request): BookingResult
    {
        if ($this->shouldFail) {
            throw new RuntimeException('Fake courier is unavailable.');
        }

        $cn = sprintf('FAKE-%08d', count($this->booked) + 1);

        $this->booked[$cn] = $request;
        $this->statuses[$cn] = ShipmentStatus::Booked;

        return new BookingResult(
            consignmentNumber: $cn,
            courier: $this->identifier(),
            labelUrl: "https://fake.test/labels/{$cn}.pdf",
            estimatedDelivery: new DateTimeImmutable(
                sprintf('+%d weekdays', $request->city->deliveryDays())
            ),
        );
    }

    public function track(string $consignmentNumber): TrackingSnapshot
    {
        return new TrackingSnapshot(
            consignmentNumber: $consignmentNumber,
            status: $this->statuses[$consignmentNumber] ?? ShipmentStatus::Booked,
            updatedAt: new DateTimeImmutable,
        );
    }

    public function cancel(string $consignmentNumber): bool
    {
        if (! isset($this->booked[$consignmentNumber])) {
            return false;
        }

        $this->statuses[$consignmentNumber] = ShipmentStatus::Cancelled;

        return true;
    }

    public function serviceableCities(): array
    {
        return City::cases();
    }

    public function supportsCashOnDelivery(): bool
    {
        return true;
    }

    // ── Test helpers ──────────────────────────────────────────────

    public function markDelivered(string $consignmentNumber): void
    {
        $this->statuses[$consignmentNumber] = ShipmentStatus::Delivered;
    }

    public function markReturned(string $consignmentNumber): void
    {
        $this->statuses[$consignmentNumber] = ShipmentStatus::ReturnedToOrigin;
    }
}
