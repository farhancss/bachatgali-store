<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * The sort orders offered on a listing page. Backed by the string that
 * appears in the URL, because the listing page must be crawlable and
 * shareable — every sort is a real, indexable URL.
 */
enum ProductSort: string
{
    case Relevance = 'relevance';
    case Newest = 'newest';
    case PriceLowToHigh = 'price-asc';
    case PriceHighToLow = 'price-desc';
    case Discount = 'discount';

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Relevance;
    }

    public function label(): string
    {
        return match ($this) {
            self::Relevance => 'Most relevant',
            self::Newest => 'Newest first',
            self::PriceLowToHigh => 'Price: low to high',
            self::PriceHighToLow => 'Price: high to low',
            self::Discount => 'Biggest discount',
        };
    }

    /** Sorting on price means sorting on the cheapest variant. */
    public function usesVariantPrice(): bool
    {
        return in_array($this, [self::PriceLowToHigh, self::PriceHighToLow], strict: true);
    }

    public function direction(): string
    {
        return $this === self::PriceLowToHigh ? 'asc' : 'desc';
    }
}
