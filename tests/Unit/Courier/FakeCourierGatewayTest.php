<?php

declare(strict_types=1);

use App\Domain\Delivery\DataObjects\BookingResult;
use App\Domain\Delivery\DataObjects\ShipmentRequest;
use App\Domain\Delivery\DataObjects\TrackingSnapshot;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Shared\Enums\City;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Courier\Contracts\CourierGateway;
use App\Infrastructure\Courier\Fake\FakeCourierGateway;

/*
| The fake is what the whole suite books against, so it has to behave like a
| courier rather than like a stub — including refusing to work. If this file
| is wrong, every test that ships an order is wrong with it.
*/

function courierRequest(?City $city = null): ShipmentRequest
{
    return new ShipmentRequest(
        orderReference: 'BG-2001',
        recipientName: 'Sana Iqbal',
        recipientPhone: '+923010000000',
        city: $city ?? City::Lahore,
        address: 'Flat 8, Askari 11',
        codAmount: Money::fromRupees(3_500),
        weightGrams: 600,
        items: [['sku' => 'BAG-TAN', 'name' => 'Tan bag', 'quantity' => 1]],
    );
}

it('satisfies the courier contract', function (): void {
    expect(new FakeCourierGateway)->toBeInstanceOf(CourierGateway::class)
        ->and((new FakeCourierGateway)->identifier())->toBe('fake');
});

it('returns a booking with a label and a delivery estimate', function (): void {
    $gateway = new FakeCourierGateway;

    $result = $gateway->book(courierRequest());

    expect($result)->toBeInstanceOf(BookingResult::class)
        ->and($result->consignmentNumber)->toBe('FAKE-00000001')
        ->and($result->courier)->toBe('fake')
        ->and($result->labelUrl)->toBe('https://fake.test/labels/FAKE-00000001.pdf')
        ->and($result->estimatedDelivery)->not->toBeNull();
});

it('issues a distinct consignment number per booking', function (): void {
    $gateway = new FakeCourierGateway;

    $first = $gateway->book(courierRequest());
    $second = $gateway->book(courierRequest());

    expect($second->consignmentNumber)->not->toBe($first->consignmentNumber)
        ->and($gateway->booked)->toHaveCount(2)
        ->and($gateway->booked[$first->consignmentNumber]->orderReference)->toBe('BG-2001');
});

it('estimates delivery from the destination city', function (): void {
    $gateway = new FakeCourierGateway;

    $lahore = $gateway->book(courierRequest(City::Lahore));
    $quetta = $gateway->book(courierRequest(City::Quetta));

    // Quetta is a five-weekday city, Lahore a two-weekday one.
    expect($quetta->estimatedDelivery)->toBeGreaterThan($lahore->estimatedDelivery);
});

it('models a courier outage instead of always succeeding', function (): void {
    $gateway = new FakeCourierGateway;
    $gateway->shouldFail = true;

    expect(fn (): BookingResult => $gateway->book(courierRequest()))
        ->toThrow(RuntimeException::class, 'Fake courier is unavailable.');

    expect($gateway->booked)->toBeEmpty();
});

it('tracks a booked consignment as booked', function (): void {
    $gateway = new FakeCourierGateway;
    $result = $gateway->book(courierRequest());

    $snapshot = $gateway->track($result->consignmentNumber);

    expect($snapshot)->toBeInstanceOf(TrackingSnapshot::class)
        ->and($snapshot->consignmentNumber)->toBe($result->consignmentNumber)
        ->and($snapshot->status)->toBe(ShipmentStatus::Booked)
        ->and($snapshot->history)->toBe([])
        ->and($snapshot->riderName)->toBeNull()
        ->and($snapshot->failureReason)->toBeNull();
});

it('reports an unknown consignment as booked rather than failing', function (): void {
    expect((new FakeCourierGateway)->track('FAKE-99999999')->status)
        ->toBe(ShipmentStatus::Booked);
});

it('reflects delivery and return through tracking', function (string $method, ShipmentStatus $expected): void {
    $gateway = new FakeCourierGateway;
    $cn = $gateway->book(courierRequest())->consignmentNumber;

    $gateway->{$method}($cn);

    expect($gateway->track($cn)->status)->toBe($expected);
})->with([
    'delivered' => ['markDelivered', ShipmentStatus::Delivered],
    'returned to origin' => ['markReturned', ShipmentStatus::ReturnedToOrigin],
]);

it('cancels a booked consignment', function (): void {
    $gateway = new FakeCourierGateway;
    $cn = $gateway->book(courierRequest())->consignmentNumber;

    expect($gateway->cancel($cn))->toBeTrue()
        ->and($gateway->track($cn)->status)->toBe(ShipmentStatus::Cancelled);
});

it('refuses to cancel a consignment it never booked', function (): void {
    expect((new FakeCourierGateway)->cancel('FAKE-99999999'))->toBeFalse();
});

it('serves every city and supports cash on delivery', function (): void {
    $gateway = new FakeCourierGateway;

    expect($gateway->serviceableCities())->toBe(City::cases())
        ->and($gateway->supportsCashOnDelivery())->toBeTrue();
});
