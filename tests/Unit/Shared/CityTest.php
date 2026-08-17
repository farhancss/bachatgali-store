<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\City;

/*
| The delivery window and the RTO flag are the two things the rest of the
| system reads off a City, so both are asserted for every case rather than a
| sample — adding a city without deciding its delivery window fails here.
*/

it('gives every city a delivery window', function (City $city): void {
    expect($city->deliveryDays())->toBeGreaterThan(0);
})->with(City::cases());

it('sets the delivery window by distance band', function (City $city, int $expected): void {
    expect($city->deliveryDays())->toBe($expected);
})->with([
    'metro' => [City::Lahore, 2],
    'metro — twin city' => [City::Rawalpindi, 2],
    'secondary' => [City::Multan, 3],
    'remote' => [City::Quetta, 5],
]);

it('flags only the remote cities as high RTO risk', function (): void {
    $highRisk = array_values(array_filter(City::cases(), fn (City $c): bool => $c->isHighRtoRisk()));

    expect($highRisk)->toEqualCanonicalizing([City::Peshawar, City::Quetta]);
});

it('titles the label from the backing value', function (): void {
    expect(City::Islamabad->label())->toBe('Islamabad')
        ->and(City::Gujranwala->label())->toBe('Gujranwala');
});

it('offers value => label options for select inputs', function (): void {
    $options = City::options();

    expect($options)->toHaveCount(count(City::cases()))
        ->and($options['karachi'])->toBe('Karachi')
        ->and(array_keys($options))->toContain('sialkot');
});
