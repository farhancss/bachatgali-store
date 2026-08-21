<?php

declare(strict_types=1);

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Exceptions\CartOperationFailed;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;

/**
 * Sets a line to an exact quantity. Zero removes the line, which is what the
 * quantity stepper in the cart drawer does when it reaches zero.
 */
final readonly class UpdateCartQuantity
{
    public function __construct(private int $maximumPerLine = 10) {}

    public function handle(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        if ($quantity > $this->maximumPerLine) {
            throw CartOperationFailed::quantityOutOfRange($quantity, $this->maximumPerLine);
        }

        $variant = $item->variant;

        if ($variant instanceof ProductVariant
            && ! $variant->backorder_allowed
            && ! $variant->is_pre_order
            && $variant->stock_quantity < $quantity) {
            throw CartOperationFailed::insufficientStock(
                $variant->sku,
                $quantity,
                $variant->stock_quantity,
            );
        }

        $item->update(['quantity' => $quantity]);

        return $item->refresh();
    }
}
