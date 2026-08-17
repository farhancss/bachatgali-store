<?php

declare(strict_types=1);

namespace App\Domain\Cod\DataObjects;

/**
 * Tunable weights for COD risk scoring.
 *
 * Injected rather than read from config inside the Action, which keeps
 * ScoreCodRisk a pure domain object: unit-testable with no application
 * container, no config, no database. The binding from config lives in
 * DomainServiceProvider.
 */
final readonly class RiskWeights
{
    public function __construct(
        public int $previousRefusals = 60,
        public int $perRefusal = 25,
        public int $firstTimeCustomer = 15,
        public int $highOrderValue = 20,
        public int $incompleteAddress = 15,
        public int $highRtoCity = 10,
    ) {}

    /** @param array<string, int> $config */
    public static function fromArray(array $config): self
    {
        return new self(
            previousRefusals: $config['previous_refusals'] ?? 60,
            perRefusal: $config['per_refusal'] ?? 25,
            firstTimeCustomer: $config['first_time_customer'] ?? 15,
            highOrderValue: $config['high_order_value'] ?? 20,
            incompleteAddress: $config['incomplete_address'] ?? 15,
            highRtoCity: $config['high_rto_city'] ?? 10,
        );
    }
}
