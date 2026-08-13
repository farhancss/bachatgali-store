<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Money is always an integer number of paisa (1/100 PKR).
 *
 * Floats are never used for currency anywhere in this codebase. The
 * architecture test `no floats in the pricing domain` enforces it.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(public int $paisa) {}

    public static function fromPaisa(int $paisa): self
    {
        return new self($paisa);
    }

    public static function fromRupees(int $rupees): self
    {
        return new self($rupees * 100);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->paisa + $other->paisa);
    }

    public function minus(self $other): self
    {
        return new self($this->paisa - $other->paisa);
    }

    public function times(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Multiplier may not be negative.');
        }

        return new self($this->paisa * $multiplier);
    }

    /**
     * Percentage of this amount, rounded half-up to the nearest paisa.
     * Integer arithmetic only — no float rounding drift.
     */
    public function percentage(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException("Percentage must be 0-100, got {$percent}.");
        }

        return new self(intdiv($this->paisa * $percent + 50, 100));
    }

    /** Never returns a negative amount — discounts clamp at zero. */
    public function minusClamped(self $other): self
    {
        return new self(max(0, $this->paisa - $other->paisa));
    }

    public function isZero(): bool
    {
        return $this->paisa === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->paisa > $other->paisa;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->paisa >= $other->paisa;
    }

    public function equals(self $other): bool
    {
        return $this->paisa === $other->paisa;
    }

    public function toRupees(): string
    {
        return number_format($this->paisa / 100, 0, '.', ',');
    }

    public function format(): string
    {
        return config('bachatgali.currency.symbol').' '.$this->toRupees();
    }

    public function jsonSerialize(): int
    {
        return $this->paisa;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
