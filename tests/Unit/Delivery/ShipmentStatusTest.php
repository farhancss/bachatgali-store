<?php

declare(strict_types=1);

use App\Domain\Delivery\Enums\ShipmentStatus;

/*
| These three predicates drive COD reconciliation: whether to keep polling a
| consignment, whether cash is owed to us, and whether the order counts
| against the RTO rate. Asserted per case so a new status has to declare
| itself rather than defaulting into "still in flight, no cash".
*/

it('treats delivered, returned and cancelled as terminal', function (): void {
    $terminal = array_values(array_filter(ShipmentStatus::cases(), fn (ShipmentStatus $s): bool => $s->isTerminal()));

    expect($terminal)->toEqualCanonicalizing([
        ShipmentStatus::Delivered,
        ShipmentStatus::ReturnedToOrigin,
        ShipmentStatus::Cancelled,
    ]);
});

it('keeps every in-flight status non-terminal', function (ShipmentStatus $status): void {
    expect($status->isTerminal())->toBeFalse();
})->with([
    ShipmentStatus::Booked,
    ShipmentStatus::PickedUp,
    ShipmentStatus::InTransit,
    ShipmentStatus::OutForDelivery,
    ShipmentStatus::DeliveryFailed,
]);

it('collects cash only on delivery', function (ShipmentStatus $status, bool $collected): void {
    expect($status->cashWasCollected())->toBe($collected);
})->with([
    'delivered' => [ShipmentStatus::Delivered, true],
    'a failed attempt is not payment' => [ShipmentStatus::DeliveryFailed, false],
    'a return is not payment' => [ShipmentStatus::ReturnedToOrigin, false],
    'still out with the rider' => [ShipmentStatus::OutForDelivery, false],
]);

it('counts only a return to origin as RTO', function (): void {
    $rto = array_values(array_filter(ShipmentStatus::cases(), fn (ShipmentStatus $s): bool => $s->countsAsRto()));

    expect($rto)->toEqualCanonicalizing([ShipmentStatus::ReturnedToOrigin]);
});

it('separates a failed attempt from a completed return', function (): void {
    // A failed attempt is retried; only the journey back to the warehouse is
    // a loss, so the two must never be conflated in the RTO numbers.
    expect(ShipmentStatus::DeliveryFailed->countsAsRto())->toBeFalse()
        ->and(ShipmentStatus::DeliveryFailed->isTerminal())->toBeFalse()
        ->and(ShipmentStatus::ReturnedToOrigin->countsAsRto())->toBeTrue();
});
