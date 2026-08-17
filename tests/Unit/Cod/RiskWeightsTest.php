<?php

declare(strict_types=1);

use App\Domain\Cod\DataObjects\RiskWeights;

/*
| fromArray() is the seam between config/bachatgali.php and the domain. A
| missing key must fall back to the shipped default rather than scoring zero,
| because a silently-zeroed weight disables a risk factor without failing.
*/

it('reads every weight from a full config array', function (): void {
    $weights = RiskWeights::fromArray([
        'previous_refusals' => 70,
        'per_refusal' => 30,
        'first_time_customer' => 12,
        'high_order_value' => 22,
        'incomplete_address' => 18,
        'high_rto_city' => 8,
    ]);

    expect($weights->previousRefusals)->toBe(70)
        ->and($weights->perRefusal)->toBe(30)
        ->and($weights->firstTimeCustomer)->toBe(12)
        ->and($weights->highOrderValue)->toBe(22)
        ->and($weights->incompleteAddress)->toBe(18)
        ->and($weights->highRtoCity)->toBe(8);
});

it('falls back to the shipped defaults for a missing key rather than scoring zero', function (): void {
    $weights = RiskWeights::fromArray(['per_refusal' => 30]);
    $defaults = new RiskWeights;

    expect($weights->perRefusal)->toBe(30)
        ->and($weights->previousRefusals)->toBe($defaults->previousRefusals)
        ->and($weights->firstTimeCustomer)->toBe($defaults->firstTimeCustomer)
        ->and($weights->highOrderValue)->toBe($defaults->highOrderValue)
        ->and($weights->incompleteAddress)->toBe($defaults->incompleteAddress)
        ->and($weights->highRtoCity)->toBe($defaults->highRtoCity);
});

it('produces the defaults from an empty config array', function (): void {
    expect(RiskWeights::fromArray([]))->toEqual(new RiskWeights);
});
