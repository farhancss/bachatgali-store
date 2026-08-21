<?php

declare(strict_types=1);

namespace App\Domain\Cart\Exceptions;

use DomainException;

final class CartOperationFailed extends DomainException
{
    public static function variantNotPurchasable(string $sku): self
    {
        return new self(sprintf('%s is not available to buy right now.', $sku));
    }

    public static function insufficientStock(string $sku, int $requested, int $available): self
    {
        return new self(sprintf(
            'Only %d of %s left — you asked for %d.',
            $available,
            $sku,
            $requested,
        ));
    }

    public static function quantityOutOfRange(int $quantity, int $max): self
    {
        return new self(sprintf('Choose between 1 and %d — you asked for %d.', $max, $quantity));
    }
}
