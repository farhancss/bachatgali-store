<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature/Database');

/*
|--------------------------------------------------------------------------
| Shared expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeMoney', function (int $paisa) {
    expect($this->value)
        ->toBeInstanceOf(App\Domain\Shared\ValueObjects\Money::class)
        ->and($this->value->paisa)->toBe($paisa);

    return $this;
});

expect()->extend('toBeRiskBand', function (App\Domain\Cod\Enums\RiskBand $band) {
    expect($this->value->band)->toBe($band);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function rupees(int $amount): App\Domain\Shared\ValueObjects\Money
{
    return App\Domain\Shared\ValueObjects\Money::fromRupees($amount);
}
