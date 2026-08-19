<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * How a product is composed. Every product owns at least one variant — a
 * simple product owns exactly one — so price and stock live in exactly one
 * place in the schema rather than being duplicated onto products.
 */
enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';
    case Bundle = 'bundle';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simple',
            self::Variable => 'Variable',
            self::Bundle => 'Bundle',
        };
    }

    /** Only variable products may carry more than one variant. */
    public function allowsMultipleVariants(): bool
    {
        return $this === self::Variable;
    }

    /** Bundles draw their stock from the components, not from their own ledger. */
    public function tracksOwnStock(): bool
    {
        return $this !== self::Bundle;
    }
}
