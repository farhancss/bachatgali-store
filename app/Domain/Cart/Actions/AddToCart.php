<?php

declare(strict_types=1);

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Exceptions\CartOperationFailed;
use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;

/**
 * Puts a variant in a cart, or increases the line that is already there.
 *
 * Stock is checked against the RESULTING quantity, not the added quantity.
 * Checking only what was just added lets someone add "2 of 3 in stock" three
 * times and reach six, which surfaces later as an oversell — and on a COD
 * order an oversell is a cancelled delivery the business already paid for.
 */
final readonly class AddToCart
{
    public function __construct(private int $maximumPerLine = 10) {}

    public function handle(Cart $cart, ProductVariant $variant, int $quantity = 1): CartItem
    {
        if ($quantity < 1 || $quantity > $this->maximumPerLine) {
            throw CartOperationFailed::quantityOutOfRange($quantity, $this->maximumPerLine);
        }

        if (! $variant->stockState()->isPurchasable()) {
            throw CartOperationFailed::variantNotPurchasable($variant->sku);
        }

        $existing = $cart->items()->where('product_variant_id', $variant->id)->first();
        $resulting = ($existing instanceof CartItem ? $existing->quantity : 0) + $quantity;

        if ($resulting > $this->maximumPerLine) {
            throw CartOperationFailed::quantityOutOfRange($resulting, $this->maximumPerLine);
        }

        $this->assertStockCovers($variant, $resulting);

        if ($existing instanceof CartItem) {
            $existing->update([
                'quantity' => $resulting,
                // Re-snapshot: the customer is agreeing to today's price for
                // the whole line, not yesterday's for part of it.
                'unit_price' => $variant->price,
            ]);

            return $existing->refresh();
        }

        return $cart->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $variant->price,
        ]);
    }

    private function assertStockCovers(ProductVariant $variant, int $quantity): void
    {
        // Backorder and pre-order lines are sellable beyond what is on hand;
        // that is the whole point of them.
        if ($variant->backorder_allowed || $variant->is_pre_order) {
            return;
        }

        if ($variant->stock_quantity < $quantity) {
            throw CartOperationFailed::insufficientStock(
                $variant->sku,
                $quantity,
                $variant->stock_quantity,
            );
        }
    }
}
