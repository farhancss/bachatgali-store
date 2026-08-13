<?php

declare(strict_types=1);

namespace App\Domain\Cod\Enums;

/**
 * The output of COD risk scoring. Determines whether an order dispatches
 * straight away, waits for a confirmation call, or is refused outright.
 */
enum RiskBand: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Blocked = 'blocked';

    public static function fromScore(int $score): self
    {
        /** @var array<string, int> $bands */
        $bands = config('bachatgali.cod.risk_bands');

        return match (true) {
            $score >= $bands['blocked'] => self::Blocked,
            $score >= $bands['high']    => self::High,
            $score >= $bands['medium']  => self::Medium,
            default                     => self::Low,
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
            self::Low     => 'Low risk',
            self::Medium  => 'Medium risk',
            self::High    => 'High risk — confirm by call',
            self::Blocked => 'Blocked',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Low     => 'success',
            self::Medium  => 'warning',
            self::High    => 'danger',
            self::Blocked => 'gray',
        };
    }
}
