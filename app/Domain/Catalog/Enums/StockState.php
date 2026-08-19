<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Availability, derived from the variant's quantity rather than stored — a
 * stored copy is one write away from disagreeing with the ledger, and the
 * disagreement always surfaces as an oversell.
 *
 * PreOrder is the exception: it is a deliberate merchandising decision about
 * an unreleased product, not a fact about the current quantity, so it is set
 * on the variant and short-circuits the derivation.
 */
enum StockState: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case Backorder = 'backorder';
    case PreOrder = 'pre_order';

    public static function forQuantity(
        int $quantity,
        int $lowStockThreshold = 0,
        bool $backorderAllowed = false,
    ): self {
        return match (true) {
            $quantity <= 0 && $backorderAllowed => self::Backorder,
            $quantity <= 0 => self::OutOfStock,
            $quantity <= $lowStockThreshold => self::LowStock,
            default => self::InStock,
        };
    }

    /** Can a customer put this in the cart right now? */
    public function isPurchasable(): bool
    {
        return $this !== self::OutOfStock;
    }

    /** Does buying this mean waiting? Drives the delivery-estimate copy. */
    public function shipsImmediately(): bool
    {
        return in_array($this, [self::InStock, self::LowStock], strict: true);
    }

    /** Nudges urgency on the product page. */
    public function isScarce(): bool
    {
        return $this === self::LowStock;
    }

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::LowStock => 'Only a few left',
            self::OutOfStock => 'Out of stock',
            self::Backorder => 'On backorder',
            self::PreOrder => 'Pre-order',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OutOfStock => 'danger',
            self::Backorder, self::PreOrder => 'info',
        };
    }
}
