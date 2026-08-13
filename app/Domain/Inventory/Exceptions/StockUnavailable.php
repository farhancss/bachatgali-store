<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class StockUnavailable extends DomainException
{
    public static function forSku(string $sku, int $requested, int $available): self
    {
        return new self(sprintf(
            'Only %d of SKU %s available, %d requested.',
            $available,
            $sku,
            $requested,
        ));
    }
}
