<?php

declare(strict_types=1);

namespace App\Domain\Cod\Actions;

use App\Domain\Cod\DataObjects\RiskAssessment;
use App\Domain\Shared\Enums\City;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Scores the RTO (return to origin) risk of a cash-on-delivery order.
 *
 * This is the highest-leverage code in the platform: RTO is the single
 * largest source of loss in a COD business, and this action is what stands
 * between a bad order and a courier collecting it.
 *
 * Weights live in config/bachatgali.php so they can be tuned against real
 * data after launch without touching code. tests/Unit/Cod/ScoreCodRiskTest
 * is table-driven, so retuning tells you exactly what behaviour changed.
 *
 * This is the worked vertical slice for the whole codebase — a single
 * public `handle()`, no framework dependencies beyond config, fully
 * unit-testable with no database.
 */
final readonly class ScoreCodRisk
{
    public function handle(
        Money $orderValue,
        City $city,
        int $previousRefusals = 0,
        bool $isFirstTimeCustomer = true,
        bool $addressLooksIncomplete = false,
    ): RiskAssessment {
        /** @var array<string, int> $weights */
        $weights = config('bachatgali.cod.risk_weights');

        $factors = [
            'previous_refusals' => $previousRefusals > 0
                ? min($weights['previous_refusals'], $previousRefusals * 25)
                : 0,

            'first_time_customer' => $isFirstTimeCustomer
                ? $weights['first_time_customer']
                : 0,

            'high_order_value' => $this->isHighValue($orderValue, $isFirstTimeCustomer)
                ? $weights['high_order_value']
                : 0,

            'incomplete_address' => $addressLooksIncomplete
                ? $weights['incomplete_address']
                : 0,

            'high_rto_city' => $city->isHighRtoRisk()
                ? $weights['high_rto_city']
                : 0,
        ];

        return RiskAssessment::fromFactors($factors);
    }

    private function isHighValue(Money $orderValue, bool $isFirstTimeCustomer): bool
    {
        $ceiling = Money::fromPaisa((int) ($isFirstTimeCustomer
            ? config('bachatgali.cod.max_order_value_new')
            : config('bachatgali.cod.max_order_value')));

        // "High value" starts at half the applicable ceiling.
        return $orderValue->isGreaterThanOrEqualTo(
            Money::fromPaisa(intdiv($ceiling->paisa, 2))
        );
    }
}
