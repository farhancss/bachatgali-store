<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Money columns are `bigInteger` paisa. PDO hands large integers back as
 * strings on some drivers, so both are accepted on the way in.
 *
 * @implements CastsAttributes<Money, Money|int>
 */
final class MoneyCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return match (true) {
            $value === null => null,
            is_int($value) => Money::fromPaisa($value),
            is_string($value) && $this->isIntegerString($value) => Money::fromPaisa((int) $value),
            default => throw new InvalidArgumentException(
                sprintf('%s holds a non-integer value and cannot be read as paisa.', $key),
            ),
        };
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->paisa,
            is_int($value) => $value,
            default => throw new InvalidArgumentException(
                sprintf('%s must be a Money instance or int paisa.', $key),
            ),
        };
    }

    private function isIntegerString(string $value): bool
    {
        return ctype_digit(ltrim($value, '-')) && $value !== '-';
    }
}
