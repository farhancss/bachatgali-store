<?php

declare(strict_types=1);

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Pricing\Models\Voucher;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows an empty cart to a new visitor', function (): void {
    get(route('cart'))->assertOk()->assertSee('Your cart is empty', escape: false);
});

it('adds a product and shows it with its total', function (): void {
    $variant = ProductVariant::factory()->priced(1_200)->create(['stock_quantity' => 5]);

    post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 2])
        ->assertRedirect()
        ->assertSessionHas('success');

    get(route('cart'))
        ->assertOk()
        ->assertSee('Rs. 2,400', escape: false)   // 2 x 1,200
        ->assertSee('Rs. 250', escape: false);    // delivery, under the threshold
});

it('gives free delivery at the threshold', function (): void {
    $variant = ProductVariant::factory()->priced(2_500)->create(['stock_quantity' => 5]);

    post(route('cart.store'), ['variant_id' => $variant->id]);

    get(route('cart'))->assertOk()->assertSee('Free', escape: false);
});

it('refuses to add more than stock allows and says why', function (): void {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 1]);

    post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 3])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(CartItem::query()->count())->toBe(0);
});

it('rejects a variant that does not exist', function (): void {
    post(route('cart.store'), ['variant_id' => 999_999])->assertSessionHasErrors('variant_id');
});

it('updates a line quantity', function (): void {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 20]);
    post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
    $item = CartItem::query()->firstOrFail();

    patch(route('cart.update', $item), ['quantity' => 4])->assertRedirect();

    expect($item->refresh()->quantity)->toBe(4);
});

it('removes a line when the quantity is set to zero', function (): void {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 20]);
    post(route('cart.store'), ['variant_id' => $variant->id]);
    $item = CartItem::query()->firstOrFail();

    patch(route('cart.update', $item), ['quantity' => 0])->assertRedirect();

    expect(CartItem::query()->count())->toBe(0);
});

it('removes a line outright', function (): void {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 20]);
    post(route('cart.store'), ['variant_id' => $variant->id]);
    $item = CartItem::query()->firstOrFail();

    delete(route('cart.destroy', $item))->assertRedirect();

    expect(CartItem::query()->count())->toBe(0);
});

it('refuses to touch a cart item belonging to someone else', function (): void {
    // Item ids are guessable. Without an ownership check anyone could empty a
    // stranger's cart by iterating them.
    $othersCart = Cart::factory()->create(['session_id' => 'someone-else']);
    $othersItem = CartItem::factory()->create(['cart_id' => $othersCart->id]);

    delete(route('cart.destroy', $othersItem))->assertNotFound();
    patch(route('cart.update', $othersItem), ['quantity' => 9])->assertNotFound();

    expect($othersItem->refresh()->quantity)->toBe(1);
});

it('applies a voucher and shows the discount', function (): void {
    $variant = ProductVariant::factory()->priced(2_000)->create(['stock_quantity' => 5]);
    Voucher::factory()->percentage(10)->create(['code' => 'SAVE10']);

    post(route('cart.store'), ['variant_id' => $variant->id]);
    post(route('cart.voucher.apply'), ['code' => 'SAVE10'])
        ->assertRedirect()
        ->assertSessionHas('success');

    get(route('cart'))->assertOk()->assertSee('SAVE10', escape: false)->assertSee('Rs. 200', escape: false);
});

it('explains why a voucher was refused instead of failing silently', function (): void {
    $variant = ProductVariant::factory()->priced(500)->create(['stock_quantity' => 5]);
    Voucher::factory()->minimumSpend(2_000)->create(['code' => 'BIGSPEND']);

    post(route('cart.store'), ['variant_id' => $variant->id]);

    post(route('cart.voucher.apply'), ['code' => 'BIGSPEND'])
        ->assertSessionHas('error', 'Your order is below the minimum for that code.');
});

it('drops a voucher that expires while the cart sits open', function (): void {
    // Re-validated on every render, not trusted from when it was entered.
    $variant = ProductVariant::factory()->priced(2_000)->create(['stock_quantity' => 5]);
    $voucher = Voucher::factory()->percentage(10)->create(['code' => 'FLEETING']);

    post(route('cart.store'), ['variant_id' => $variant->id]);
    post(route('cart.voucher.apply'), ['code' => 'FLEETING'])->assertSessionHas('success');

    $voucher->update(['expires_at' => now()->subMinute()]);

    get(route('cart'))->assertOk()->assertDontSee('Discount', escape: false);
});

it('removes an applied voucher', function (): void {
    $variant = ProductVariant::factory()->priced(2_000)->create(['stock_quantity' => 5]);
    Voucher::factory()->percentage(10)->create(['code' => 'SAVE10']);

    post(route('cart.store'), ['variant_id' => $variant->id]);
    post(route('cart.voucher.apply'), ['code' => 'SAVE10']);
    delete(route('cart.voucher.remove'))->assertRedirect();

    expect(Cart::query()->firstOrFail()->voucher_code)->toBeNull();
});

it('adds to cart from the product page form', function (): void {
    $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);
    $product = $variant->product;

    get(route('product', $product))->assertOk()->assertSee(route('cart.store'), escape: false);
});
