<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domain\Cart\Models\Cart;
use App\Domain\Pricing\Actions\ApplyVoucher;
use App\Domain\Pricing\Actions\CalculateCartTotals;
use App\Domain\Pricing\DataObjects\CartTotals;
use App\Domain\Pricing\Models\Voucher;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves the request's cart and prices it.
 *
 * Lives in Http, not Domain: it needs the session and the clock, and pulling
 * either into the domain layer would cost every cart Action its unit test.
 */
final readonly class CurrentCart
{
    public function __construct(
        private CalculateCartTotals $totals,
        private ApplyVoucher $applyVoucher,
    ) {}

    /**
     * The session key holding this visitor's cart token.
     *
     * A token, not the session id. Laravel regenerates the session id on
     * login and on invalidation, and keying the cart on it means a customer
     * signing in silently loses everything they had added.
     */
    private const string TOKEN_KEY = 'cart_token';

    /** Finds this visitor's cart, or null if they have never added anything. */
    public function get(Request $request): ?Cart
    {
        $userId = $request->user()?->getAuthIdentifier();
        $token = $request->session()->get(self::TOKEN_KEY);

        return Cart::query()
            ->when(
                $userId !== null,
                fn ($q) => $q->where('user_id', $userId),
                fn ($q) => is_string($token)
                    ? $q->where('session_id', $token)->whereNull('user_id')
                    : $q->whereRaw('1 = 0'),
            )
            ->with(['items.variant.product'])
            ->first();
    }

    /**
     * The cart to write into. Separate from get() rather than a boolean flag,
     * because a flag that changes the return type from Cart|null to Cart is
     * invisible to the type system and pushes null checks onto every caller.
     */
    public function getOrCreate(Request $request): Cart
    {
        $existing = $this->get($request);

        if ($existing instanceof Cart) {
            return $existing;
        }

        $userId = $request->user()?->getAuthIdentifier();
        $token = $request->session()->get(self::TOKEN_KEY);

        if (! is_string($token)) {
            $token = Str::random(40);
            $request->session()->put(self::TOKEN_KEY, $token);
        }

        return Cart::query()->create([
            'session_id' => $token,
            'user_id' => $userId,
            'last_activity_at' => now(),
        ])->load('items.variant.product');
    }

    /** Prices a cart, resolving and re-validating its voucher each time. */
    public function totals(?Cart $cart): CartTotals
    {
        if (! $cart instanceof Cart || $cart->items->isEmpty()) {
            return CartTotals::empty();
        }

        $lines = $cart->pricingLines();

        $subtotal = $this->totals->handle($lines)->subtotal;

        // Re-checked on every render rather than trusted from when it was
        // entered: a voucher can expire or hit its usage cap while the cart
        // sits open, and a stale discount at checkout is money given away.
        $voucher = $cart->voucher_code === null
            ? null
            : $this->applyVoucher->handle(
                Voucher::query()->forCode($cart->voucher_code)->first(),
                $subtotal,
                new DateTimeImmutable,
            );

        return $this->totals->handle($lines, $voucher);
    }
}
