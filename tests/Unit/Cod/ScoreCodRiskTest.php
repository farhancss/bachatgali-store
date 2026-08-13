<?php

declare(strict_types=1);

use App\Domain\Cod\Actions\ScoreCodRisk;
use App\Domain\Cod\Enums\RiskBand;
use App\Domain\Shared\Enums\City;
use App\Domain\Shared\ValueObjects\Money;

/*
| Table-driven on purpose. When the weights in config/bachatgali.php are
| retuned against real RTO data, this file tells you precisely which
| scenarios changed band — which is the whole point of testing a model.
*/

it('scores a returning customer in a low-RTO city as low risk', function () {
    $assessment = app(ScoreCodRisk::class)->handle(
        orderValue: Money::fromRupees(2_000),
        city: City::Lahore,
        previousRefusals: 0,
        isFirstTimeCustomer: false,
    );

    expect($assessment)->toBeRiskBand(RiskBand::Low)
        ->and($assessment->band->canDispatch())->toBeTrue();
});

it('escalates a customer with prior refusals', function (int $refusals, RiskBand $expected) {
    $assessment = app(ScoreCodRisk::class)->handle(
        orderValue: Money::fromRupees(2_000),
        city: City::Lahore,
        previousRefusals: $refusals,
        isFirstTimeCustomer: false,
    );

    expect($assessment)->toBeRiskBand($expected);
})->with([
    'one refusal'    => [1, RiskBand::Low],
    'two refusals'   => [2, RiskBand::Medium],
    'three refusals' => [3, RiskBand::High],
]);

it('sends a high-risk order to a confirmation call rather than a courier', function () {
    $assessment = app(ScoreCodRisk::class)->handle(
        orderValue: Money::fromRupees(14_000),
        city: City::Quetta,
        previousRefusals: 0,
        isFirstTimeCustomer: true,
        addressLooksIncomplete: true,
    );

    expect($assessment->band->requiresConfirmationCall())->toBeTrue()
        ->and($assessment->band->canDispatch())->toBeFalse();
});

it('records which factors contributed, for the ops queue', function () {
    $assessment = app(ScoreCodRisk::class)->handle(
        orderValue: Money::fromRupees(200),
        city: City::Quetta,
        previousRefusals: 0,
        isFirstTimeCustomer: true,
    );

    expect($assessment->reasons())
        ->toContain('first_time_customer')
        ->toContain('high_rto_city')
        ->not->toContain('previous_refusals');
});

it('never produces a score above 100', function () {
    $assessment = app(ScoreCodRisk::class)->handle(
        orderValue: Money::fromRupees(49_000),
        city: City::Quetta,
        previousRefusals: 10,
        isFirstTimeCustomer: true,
        addressLooksIncomplete: true,
    );

    expect($assessment->score)->toBeLessThanOrEqual(100)
        ->and($assessment->band)->toBe(RiskBand::Blocked);
});
