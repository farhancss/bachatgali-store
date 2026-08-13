<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Exceptions;

use App\Domain\Shared\ValueObjects\Money;
use DomainException;

final class CodLimitExceeded extends DomainException
{
    public static function for(string $phone, Money $amount): self
    {
        return new self(sprintf(
            'COD order of %s refused for %s — risk band is blocked.',
            $amount->format(),
            $phone,
        ));
    }

    public static function overLimit(Money $amount, Money $limit): self
    {
        return new self(sprintf(
            'COD order of %s exceeds the limit of %s.',
            $amount->format(),
            $limit->format(),
        ));
    }
}
