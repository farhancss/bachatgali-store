<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Serviceable cities. `rtoRisk` is the historical return-to-origin weighting
 * used by the COD risk scorer; revisit it quarterly against real data.
 */
enum City: string
{
    case Lahore = 'lahore';
    case Karachi = 'karachi';
    case Islamabad = 'islamabad';
    case Rawalpindi = 'rawalpindi';
    case Faisalabad = 'faisalabad';
    case Multan = 'multan';
    case Peshawar = 'peshawar';
    case Quetta = 'quetta';
    case Sialkot = 'sialkot';
    case Gujranwala = 'gujranwala';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Standard delivery window in working days. */
    public function deliveryDays(): int
    {
        return match ($this) {
            self::Lahore, self::Karachi, self::Islamabad, self::Rawalpindi => 2,
            self::Faisalabad, self::Multan, self::Sialkot, self::Gujranwala => 3,
            self::Peshawar, self::Quetta => 5,
        };
    }

    public function isHighRtoRisk(): bool
    {
        return in_array($this, [self::Quetta, self::Peshawar], strict: true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->all();
    }
}
