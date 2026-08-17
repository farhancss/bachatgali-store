<?php

declare(strict_types=1);

namespace App\Domain\Cod\Actions;

use App\Domain\Cod\DataObjects\CodLimits;
use App\Domain\Cod\DataObjects\RiskAssessment;
use App\Domain\Cod\DataObjects\RiskWeights;
use App\Domain\Shared\Enums\City;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Scores the RTO (return-to-origin) risk of a cash-on-delivery order.
 *
 * This is the highest-leverage code in the platform: RTO is the largest
 * source of loss in a COD business, and this action stands between a bad
 * order and a courier collecting it.
 *
 * Weights and limits are injected (bound from config in DomainServiceProvider)
 * so this class has zero framework dependencies — no container, no config
 * helper, no database. That is what makes it a genuine unit test.
 *
 * This is the reference implementation for every Action in the codebase.
 */
final readonly class ScoreCodRisk
{
    public function __construct(
        private RiskWeights $weights,
        private CodLimits $limits,
    ) {}

    public function handle(
        Money $orderValue,
        City $city,
        int $previousRefusals = 0,
        bool $isFirstTimeCustomer = true,
        bool $addressLooksIncomplete = false,
    ): RiskAssessment {
        $isHighValue = $orderValue->isGreaterThanOrEqualTo(
            $this->limits->highValueThresholdFor($isFirstTimeCustomer),
        );

        return RiskAssessment::fromFactors([
            'previous_refusals' => $previousRefusals > 0
                ? min($this->weights->previousRefusals, $previousRefusals * $this->weights->perRefusal)
                : 0,

            'first_time_customer' => $isFirstTimeCustomer
                ? $this->weights->firstTimeCustomer
                : 0,

            'high_order_value' => $isHighValue
                ? $this->weights->highOrderValue
                : 0,

            'incomplete_address' => $addressLooksIncomplete
                ? $this->weights->incompleteAddress
                : 0,

            'high_rto_city' => $city->isHighRtoRisk()
                ? $this->weights->highRtoCity
                : 0,
        ]);
    }
}
