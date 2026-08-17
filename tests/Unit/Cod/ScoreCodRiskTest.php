<?php

declare(strict_types=1);

use App\Domain\Cod\Actions\ScoreCodRisk;
use App\Domain\Cod\DataObjects\CodLimits;
use App\Domain\Cod\DataObjects\RiskWeights;
use App\Domain\Cod\Enums\RiskBand;
use App\Domain\Shared\Enums\City;
use App\Domain\Shared\ValueObjects\Money;

/*
| Table-driven on purpose. When the weights in config/bachatgali.php are
| retuned against real RTO data, this file tells you precisely which
| scenarios changed band — which is the whole point of testing a model.
|
| Note there is no app() or config() here: ScoreCodRisk takes its tunables
| as constructor arguments, so this is a true unit test.
*/

function scorer(?RiskWeights $weights = null): ScoreCodRisk
{
    return new ScoreCodRisk(
        $weights ?? new RiskWeights,
        new CodLimits(
            maxOrderValue: Money::fromRupees(50_000),
            maxOrderValueNewCustomer: Money::fromRupees(15_000),
        ),
    );
}

it('scores a returning customer in a low-RTO city as low risk', function () {
    $assessment = scorer()->handle(
        orderValue: Money::fromRupees(2_000),
        city: City::Lahore,
        previousRefusals: 0,
        isFirstTimeCustomer: false,
    );

    expect($assessment)->toBeRiskBand(RiskBand::Low)
        ->and($assessment->score)->toBe(0)
        ->and($assessment->band->canDispatch())->toBeTrue();
});

it('escalates a customer with prior refusals', function (int $refusals, int $expectedScore, RiskBand $expectedBand) {
    $assessment = scorer()->handle(
        orderValue: Money::fromRupees(2_000),
        city: City::Lahore,
        previousRefusals: $refusals,
        isFirstTimeCustomer: false,
    );

    expect($assessment->score)->toBe($expectedScore)
        ->and($assessment->band)->toBe($expectedBand);
})->with([
    'one refusal'    => [1, 25, RiskBand::Low],
    'two refusals'   => [2, 50, RiskBand::Medium],
    'three refusals' => [3, 60, RiskBand::High],
    'caps at six'    => [6, 60, RiskBand::High],
]);

it('sends a high-risk order to a confirmation call rather than a courier', function () {
    // First-time buyer, Rs. 14,000 (over half the Rs. 15,000 new-customer
    // ceiling), high-RTO city, vague address: 15 + 20 + 10 + 15 = 60.
    $assessment = scorer()->handle(
        orderValue: Money::fromRupees(14_000),
        city: City::Quetta,
        previousRefusals: 0,
        isFirstTimeCustomer: true,
        addressLooksIncomplete: true,
    );

    expect($assessment->score)->toBe(60)
        ->and($assessment->band)->toBe(RiskBand::High)
        ->and($assessment->band->requiresConfirmationCall())->toBeTrue()
        ->and($assessment->band->canDispatch())->toBeFalse();
});

it('applies the lower ceiling to first-time customers', function () {
    // Rs. 9,000 is over half the new-customer ceiling but well under half
    // the returning-customer ceiling, so it only counts as high value once.
    $forNewCustomer = scorer()->handle(
        orderValue: Money::fromRupees(9_000),
        city: City::Lahore,
        isFirstTimeCustomer: true,
    );

    $forReturning = scorer()->handle(
        orderValue: Money::fromRupees(9_000),
        city: City::Lahore,
        isFirstTimeCustomer: false,
    );

    expect($forNewCustomer->factors['high_order_value'])->toBe(20)
        ->and($forReturning->factors['high_order_value'])->toBe(0);
});

it('records which factors contributed, for the ops queue', function () {
    $assessment = scorer()->handle(
        orderValue: Money::fromRupees(200),
        city: City::Quetta,
        previousRefusals: 0,
        isFirstTimeCustomer: true,
    );

    expect($assessment->reasons())
        ->toContain('first_time_customer')
        ->toContain('high_rto_city')
        ->not->toContain('previous_refusals')
        ->and($assessment->score)->toBe(25)
        ->and($assessment->band)->toBe(RiskBand::Low);
});

it('blocks the worst case and never exceeds a score of 100', function () {
    $assessment = scorer()->handle(
        orderValue: Money::fromRupees(49_000),
        city: City::Quetta,
        previousRefusals: 10,
        isFirstTimeCustomer: true,
        addressLooksIncomplete: true,
    );

    expect($assessment->score)->toBe(100)
        ->and($assessment->band)->toBe(RiskBand::Blocked)
        ->and($assessment->band->canDispatch())->toBeFalse();
});

it('honours retuned weights', function () {
    $lenient = new RiskWeights(previousRefusals: 10, perRefusal: 5);

    $assessment = scorer($lenient)->handle(
        orderValue: Money::fromRupees(2_000),
        city: City::Lahore,
        previousRefusals: 3,
        isFirstTimeCustomer: false,
    );

    expect($assessment->score)->toBe(10)
        ->and($assessment->band)->toBe(RiskBand::Low);
});
