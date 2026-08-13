<?php

declare(strict_types=1);

namespace App\Domain\Cod\DataObjects;

use App\Domain\Cod\Enums\RiskBand;

final readonly class RiskAssessment
{
    /** @param array<string, int> $factors Factor name => points contributed. */
    public function __construct(
        public int $score,
        public RiskBand $band,
        public array $factors,
    ) {}

    /** @param array<string, int> $factors */
    public static function fromFactors(array $factors): self
    {
        $score = min(100, array_sum($factors));

        return new self($score, RiskBand::fromScore($score), $factors);
    }

    /** @return array<int, string> Human-readable reasons, for the ops queue. */
    public function reasons(): array
    {
        return array_keys(array_filter($this->factors, static fn (int $p): bool => $p > 0));
    }
}
