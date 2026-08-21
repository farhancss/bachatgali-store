<?php

declare(strict_types=1);

use App\Domain\Cart\Actions\AddToCart;
use App\Domain\Cart\Actions\MergeGuestCart;
use App\Domain\Cart\Actions\UpdateCartQuantity;
use App\Domain\Cart\Exceptions\CartOperationFailed;
use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Shared\ValueObjects\Money;

it('adds a variant to an empty cart at its current price', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->priced(1_200)->create(['stock_quantity' => 10]);

    $item = new AddToCart()->handle($cart, $variant, 2);

    expect($item->quantity)->toBe(2)
        ->and($item->unit_price)->toBeMoney(120_000)
        ->and($item->lineTotal())->toBeMoney(240_000);
});

it('increases the existing line rather than adding a second one', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['stock_quantity' => 20]);

    new AddToCart()->handle($cart, $variant, 2);
    new AddToCart()->handle($cart, $variant, 3);

    expect($cart->items()->count())->toBe(1)
        ->and($cart->items()->first()?->quantity)->toBe(5);
});

it('checks stock against the resulting quantity, not the added quantity', function (): void {
    // Adding "2 of 3 in stock" repeatedly must not reach six. On a COD order
    // an oversell is a cancelled delivery the business already paid for.
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['stock_quantity' => 3]);

    new AddToCart()->handle($cart, $variant, 2);

    expect(fn (): CartItem => new AddToCart()->handle($cart, $variant, 2))
        ->toThrow(CartOperationFailed::class, 'Only 3 of');

    expect($cart->items()->first()?->quantity)->toBe(2);
});

it('refuses a variant that is not purchasable', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->outOfStock()->create();

    expect(fn (): CartItem => new AddToCart()->handle($cart, $variant))
        ->toThrow(CartOperationFailed::class, 'not available to buy');
});

it('allows a backorder line past on-hand stock', function (): void {
    // Selling beyond stock is the entire point of backorder.
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->backorderable()->create();

    expect(new AddToCart()->handle($cart, $variant, 5)->quantity)->toBe(5);
});

it('enforces a per-line maximum', function (int $quantity): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['stock_quantity' => 500]);

    expect(fn (): CartItem => new AddToCart()->handle($cart, $variant, $quantity))
        ->toThrow(CartOperationFailed::class);
})->with(['zero' => 0, 'negative' => -1, 'over the cap' => 11]);

it('re-snapshots the price when a line grows', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->priced(1_000)->create(['stock_quantity' => 50]);

    new AddToCart()->handle($cart, $variant, 1);

    $variant->update(['price' => Money::fromRupees(1_400)]);
    $item = new AddToCart()->handle($cart, $variant->refresh(), 1);

    // The customer agrees to today's price for the whole line.
    expect($item->unit_price)->toBeMoney(140_000)
        ->and($item->quantity)->toBe(2);
});

it('sets a line to an exact quantity', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['stock_quantity' => 50]);
    $item = new AddToCart()->handle($cart, $variant, 4);

    expect(new UpdateCartQuantity()->handle($item, 2)?->quantity)->toBe(2);
});

it('removes the line when the stepper reaches zero', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['stock_quantity' => 50]);
    $item = new AddToCart()->handle($cart, $variant, 2);

    expect(new UpdateCartQuantity()->handle($item, 0))->toBeNull()
        ->and($cart->items()->count())->toBe(0);
});

it('refuses to raise a line beyond available stock', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['stock_quantity' => 3]);
    $item = new AddToCart()->handle($cart, $variant, 1);

    expect(fn (): ?CartItem => new UpdateCartQuantity()->handle($item, 5))
        ->toThrow(CartOperationFailed::class);
});

it('notices when the catalog price has moved since a line was added', function (): void {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->priced(1_000)->create(['stock_quantity' => 10]);
    $item = new AddToCart()->handle($cart, $variant, 1);

    expect($item->load('variant')->priceHasChanged())->toBeFalse();

    $variant->update(['price' => Money::fromRupees(1_250)]);

    expect($item->load('variant')->priceHasChanged())->toBeTrue();
});

it('sums quantities when merging a guest cart, rather than replacing', function (): void {
    // Replacing silently loses what the customer added before signing in.
    $variant = ProductVariant::factory()->create(['stock_quantity' => 50]);
    $guest = Cart::factory()->create();
    $user = Cart::factory()->create(['user_id' => 1]);

    new AddToCart()->handle($guest, $variant, 2);
    new AddToCart()->handle($user, $variant, 3);

    $merged = new MergeGuestCart()->handle($guest, $user);

    expect($merged->items)->toHaveCount(1)
        ->and($merged->items->first()?->quantity)->toBe(5)
        ->and(Cart::query()->find($guest->id))->toBeNull();
});

it('carries guest lines the signed-in cart does not have', function (): void {
    $a = ProductVariant::factory()->create(['stock_quantity' => 50]);
    $b = ProductVariant::factory()->create(['stock_quantity' => 50]);
    $guest = Cart::factory()->create();
    $user = Cart::factory()->create(['user_id' => 1]);

    new AddToCart()->handle($guest, $a, 1);
    new AddToCart()->handle($user, $b, 1);

    expect(new MergeGuestCart()->handle($guest, $user)->items)->toHaveCount(2);
});

it('never lets a merge exceed the per-line maximum', function (): void {
    $variant = ProductVariant::factory()->backorderable()->create();
    $guest = Cart::factory()->create();
    $user = Cart::factory()->create(['user_id' => 1]);

    new AddToCart()->handle($guest, $variant, 8);
    new AddToCart()->handle($user, $variant, 7);

    expect(new MergeGuestCart()->handle($guest, $user)->items->first()?->quantity)->toBe(10);
});

it('keeps the signed-in voucher when merging', function (): void {
    $guest = Cart::factory()->create(['voucher_code' => 'GUEST']);
    $user = Cart::factory()->create(['user_id' => 1, 'voucher_code' => 'MINE']);

    expect(new MergeGuestCart()->handle($guest, $user)->voucher_code)->toBe('MINE');
});

it('adopts the guest voucher when the signed-in cart has none', function (): void {
    $guest = Cart::factory()->create(['voucher_code' => 'GUEST']);
    $user = Cart::factory()->create(['user_id' => 1]);

    expect(new MergeGuestCart()->handle($guest, $user)->voucher_code)->toBe('GUEST');
});

it('exposes pricing lines in the shape the calculator expects', function (): void {
    $cart = Cart::factory()->create();
    new AddToCart()->handle($cart, ProductVariant::factory()->priced(500)->create(['stock_quantity' => 9]), 2);

    $lines = $cart->load('items')->pricingLines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['unit_price'])->toBeMoney(50_000)
        ->and($lines[0]['quantity'])->toBe(2)
        ->and($cart->itemCount())->toBe(2)
        ->and($cart->isEmpty())->toBeFalse();
});
