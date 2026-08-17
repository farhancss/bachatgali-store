<?php

declare(strict_types=1);

use App\Domain\Delivery\DataObjects\ShipmentRequest;
use App\Domain\Shared\Enums\City;
use App\Domain\Shared\ValueObjects\Money;

/** @param array<int, array{sku: string, name: string, quantity: int}> $items */
function shipmentRequest(?Money $codAmount = null, array $items = []): ShipmentRequest
{
    return new ShipmentRequest(
        orderReference: 'BG-1001',
        recipientName: 'Ayesha Khan',
        recipientPhone: '+923001234567',
        city: City::Lahore,
        address: 'House 12, Street 4, Model Town',
        codAmount: $codAmount ?? Money::fromRupees(4_299),
        weightGrams: 850,
        items: $items !== [] ? $items : [
            ['sku' => 'TSH-BLK-M', 'name' => 'Black tee', 'quantity' => 2],
        ],
    );
}

it('is a cash-on-delivery shipment when there is cash to collect', function (): void {
    expect(shipmentRequest()->isCashOnDelivery())->toBeTrue();
});

it('is not cash on delivery when the amount is zero', function (): void {
    // A prepaid or fully voucher-covered order still ships, but the rider
    // must not be asked to collect anything.
    expect(shipmentRequest(Money::zero())->isCashOnDelivery())->toBeFalse();
});

it('counts units rather than lines', function (): void {
    $request = shipmentRequest(items: [
        ['sku' => 'TSH-BLK-M', 'name' => 'Black tee', 'quantity' => 2],
        ['sku' => 'MUG-WHT', 'name' => 'White mug', 'quantity' => 3],
    ]);

    expect($request->itemCount())->toBe(5);
});

it('counts nothing for an empty basket', function (): void {
    expect(shipmentRequest(items: [['sku' => 'X', 'name' => 'X', 'quantity' => 0]])->itemCount())->toBe(0);
});

it('keeps the optional delivery hints nullable', function (): void {
    $bare = shipmentRequest();

    $annotated = new ShipmentRequest(
        orderReference: 'BG-1002',
        recipientName: 'Bilal Ahmed',
        recipientPhone: '+923331234567',
        city: City::Quetta,
        address: 'Shop 3, Jinnah Road',
        codAmount: Money::fromRupees(1_500),
        weightGrams: 300,
        items: [['sku' => 'CAP-RED', 'name' => 'Red cap', 'quantity' => 1]],
        landmark: 'Opposite the pharmacy',
        instructions: 'Call on arrival',
    );

    expect($bare->landmark)->toBeNull()
        ->and($bare->instructions)->toBeNull()
        ->and($annotated->landmark)->toBe('Opposite the pharmacy')
        ->and($annotated->instructions)->toBe('Call on arrival');
});
