<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Pricing\Actions\ApplyVoucher;
use App\Domain\Pricing\Models\Voucher;
use App\Http\Support\CurrentCart;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The voucher on a cart, as its own resource: store applies one, destroy
 * removes it. Keeping these off CartController keeps both controllers
 * RESTful, which the architecture tests enforce.
 */
final readonly class CartVoucherController
{
    public function __construct(private CurrentCart $carts) {}

    public function store(Request $request, ApplyVoucher $applyVoucher): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        $cart = $this->carts->getOrCreate($request);
        $subtotal = $this->carts->totals($cart)->subtotal;

        $result = $applyVoucher->handle(
            Voucher::query()->forCode($validated['code'])->first(),
            $subtotal,
            new DateTimeImmutable,
        );

        if (! $result->applied) {
            return back()->with('error', $result->message());
        }

        $cart->update(['voucher_code' => $result->code]);

        return back()->with('success', 'Voucher applied.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->carts->get($request)?->update(['voucher_code' => null]);

        return back()->with('success', 'Voucher removed.');
    }
}
