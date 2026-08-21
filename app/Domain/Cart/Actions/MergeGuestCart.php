<?php

declare(strict_types=1);

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;

/**
 * Folds a guest cart into the cart of the customer who just signed in.
 *
 * Quantities are summed and then clamped, not replaced. Replacing silently
 * loses whatever the customer added before signing in, which they will not
 * notice until the order arrives short.
 */
final readonly class MergeGuestCart
{
    public function __construct(private int $maximumPerLine = 10) {}

    public function handle(Cart $guestCart, Cart $userCart): Cart
    {
        $guestCart->loadMissing('items');
        $userCart->loadMissing('items');

        foreach ($guestCart->items as $guestItem) {
            $existing = $userCart->items->firstWhere('product_variant_id', $guestItem->product_variant_id);

            if ($existing instanceof CartItem) {
                $existing->update([
                    'quantity' => min($this->maximumPerLine, $existing->quantity + $guestItem->quantity),
                ]);

                continue;
            }

            $userCart->items()->create([
                'product_variant_id' => $guestItem->product_variant_id,
                'quantity' => min($this->maximumPerLine, $guestItem->quantity),
                'unit_price' => $guestItem->unit_price,
            ]);
        }

        // A guest voucher carries over only if the signed-in cart has none,
        // so merging can never silently drop a discount already applied.
        if ($userCart->voucher_code === null && $guestCart->voucher_code !== null) {
            $userCart->update(['voucher_code' => $guestCart->voucher_code]);
        }

        $guestCart->delete();

        return $userCart->refresh()->load('items');
    }
}
