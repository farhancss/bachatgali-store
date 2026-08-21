<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\HomeController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\SearchController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CartVoucherController;
use Illuminate\Support\Facades\Route;
use Spatie\ResponseCache\Middlewares\CacheResponse;

/*
|--------------------------------------------------------------------------
| Storefront routes
|--------------------------------------------------------------------------
| Rendering strategy (see docs/adr/0003-hybrid-blade-inertia-rendering.md):
|
|   Blade   → catalog pages. Server-rendered, cached, crawlable.
|   Inertia → cart, checkout, account. Stateful, private, never indexed.
|
| Controllers arrive in phase 1–3. This file documents the intended URL
| taxonomy now so the 301 map from WooCommerce can be written against it.
*/

// ── Catalog (Blade, edge-cacheable) ───────────────────────────
// CacheResponse only stores what CatalogPages allows: guest GETs on these
// three route names. Search is deliberately outside the cached group — its
// responses vary by query string and go stale immediately.
Route::middleware(CacheResponse::class)->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/c/{category:slug}', CategoryController::class)->name('category');
    Route::get('/p/{product:slug}', ProductController::class)->name('product');
});

Route::get('/search', SearchController::class)->name('search');

// ── Cart (plain forms, never cached) ──────────────────────────
// Forms and redirects rather than a JSON API: on a patchy mobile network a
// customer who loses their connection still has a working cart.
Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');

// Static segments before the wildcard: registered the other way round,
// DELETE /cart/voucher matches /cart/{item} and tries to bind "voucher" as
// an item id, which 404s.
Route::post('/cart/voucher', [CartVoucherController::class, 'store'])->name('cart.voucher.apply');
Route::delete('/cart/voucher', [CartVoucherController::class, 'destroy'])->name('cart.voucher.remove');

Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');

// ── Shop (Inertia) ────────────────────────────────────────────
// Route::get('/checkout',                    [CheckoutController::class, 'show'])->name('checkout');
// Route::post('/checkout',                   [CheckoutController::class, 'place'])->name('checkout.place');
// Route::post('/checkout/otp/verify',        VerifyOtpController::class)->name('checkout.otp.verify');

// ── Public order tracking (no login: order ref + phone) ────────
// Route::get('/track/{order:reference}',     TrackOrderController::class)->name('orders.track');

// ── Courier webhooks ──────────────────────────────────────────
// Route::post('/webhooks/courier/{driver}',  CourierWebhookController::class)
//     ->middleware('verify.courier.signature');
