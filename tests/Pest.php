<?php

declare(strict_types=1);

use App\Domain\Cod\Enums\RiskBand;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
| Feature tests get the application and a fresh database.
|
| Unit tests deliberately do NOT get the application: every class under
| app/Domain is constructed directly with its dependencies. If a unit test
| ever needs app() or config(), that is a signal the class under test has
| picked up a framework dependency it should not have.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeMoney', function (int $paisa): object {
    expect($this->value)
        ->toBeInstanceOf(Money::class)
        ->and($this->value->paisa)->toBe($paisa);

    return $this;
});

expect()->extend('toBeRiskBand', function (RiskBand $band): object {
    expect($this->value->band)->toBe($band);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function rupees(int $amount): Money
{
    return Money::fromRupees($amount);
}
