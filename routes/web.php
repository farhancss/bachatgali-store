<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

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

Route::get('/', fn (): Factory|View => view('catalog.home'))->name('home');

// ── Catalog (Blade, cached) ───────────────────────────────────
// Route::get('/c/{category:slug}',           CategoryController::class)->name('category');
// Route::get('/p/{product:slug}',            ProductController::class)->name('product');
// Route::get('/search',                      SearchController::class)->name('search');

// ── Shop (Inertia) ────────────────────────────────────────────
// Route::get('/cart',                        CartController::class)->name('cart');
// Route::get('/checkout',                    [CheckoutController::class, 'show'])->name('checkout');
// Route::post('/checkout',                   [CheckoutController::class, 'place'])->name('checkout.place');
// Route::post('/checkout/otp/verify',        VerifyOtpController::class)->name('checkout.otp.verify');

// ── Public order tracking (no login: order ref + phone) ────────
// Route::get('/track/{order:reference}',     TrackOrderController::class)->name('orders.track');

// ── Courier webhooks ──────────────────────────────────────────
// Route::post('/webhooks/courier/{driver}',  CourierWebhookController::class)
//     ->middleware('verify.courier.signature');
