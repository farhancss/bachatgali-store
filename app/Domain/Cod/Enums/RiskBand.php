<?php

declare(strict_types=1);

namespace App\Domain\Cod\Enums;

/**
 * The output of COD risk scoring. Determines whether an order dispatches
 * immediately, waits for a confirmation call, or is refused outright.
 *
 * The thresholds are domain rules rather than configuration: changing them
 * changes what the bands *mean*, which should be a deliberate code change
 * reviewed alongside the tests. The tunable part is the weights that produce
 * the score — see RiskWeights.
 */
enum RiskBand: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Blocked = 'blocked';

    public const int MEDIUM_THRESHOLD = 30;

    public const int HIGH_THRESHOLD = 55;

    public const int BLOCKED_THRESHOLD = 85;

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= self::BLOCKED_THRESHOLD => self::Blocked,
            $score >= self::HIGH_THRESHOLD => self::High,
            $score >= self::MEDIUM_THRESHOLD => self::Medium,
            default => self::Low,
        };
    }

    /** High-risk orders go to a human before they go to a courier. */
    public function requiresConfirmationCall(): bool
    {
        return $this === self::High;
    }

    public function canDispatch(): bool
    {
        return $this === self::Low || $this === self::Medium;
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low risk',
            self::Medium => 'Medium risk',
            self::High => 'High risk — confirm by call',
            self::Blocked => 'Blocked',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Blocked => 'gray',
        };
    }
}
