<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Cart\Actions\AddToCart;
use App\Domain\Cart\Actions\UpdateCartQuantity;
use App\Domain\Cart\Exceptions\CartOperationFailed;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;
use App\Http\Support\CurrentCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The cart works as plain forms and redirects.
 *
 * That is deliberate: a customer who loses their connection mid-checkout on a
 * patchy mobile network still has a working cart, and every action degrades
 * to a normal POST. The drawer enhances this rather than replacing it.
 */
final readonly class CartController
{
    public function __construct(private CurrentCart $carts) {}

    public function show(Request $request): View
    {
        $cart = $this->carts->get($request);

        return view('shop.cart', [
            'cart' => $cart,
            'totals' => $this->carts->totals($cart),
            'categories' => [],
        ]);
    }

    public function store(Request $request, AddToCart $addToCart): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $variant = ProductVariant::query()->where('id', $validated['variant_id'])->firstOrFail();
        $cart = $this->carts->getOrCreate($request);

        try {
            $addToCart->handle($cart, $variant, (int) ($validated['quantity'] ?? 1));
        } catch (CartOperationFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Added to your cart.');
    }

    public function update(Request $request, CartItem $item, UpdateCartQuantity $update): RedirectResponse
    {
        $this->assertBelongsToVisitor($request, $item);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        try {
            $update->handle($item, (int) $validated['quantity']);
        } catch (CartOperationFailed $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->assertBelongsToVisitor($request, $item);

        $item->delete();

        return back()->with('success', 'Removed from your cart.');
    }

    /**
     * A cart item id is guessable, so ownership is checked on every mutation.
     * Without this, anyone could empty a stranger's cart by iterating ids.
     */
    private function assertBelongsToVisitor(Request $request, CartItem $item): void
    {
        $cart = $this->carts->get($request);

        if (! $cart || $item->cart_id !== $cart->id) {
            throw new NotFoundHttpException;
        }
    }
}
