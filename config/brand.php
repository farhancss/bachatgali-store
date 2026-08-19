<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Brand
|--------------------------------------------------------------------------
| Everything that changes when this platform is deployed for a different
| store. Nothing in app/ or resources/ may hardcode a store name, a colour
| or a support number — it comes from here, and here reads from .env.
|
| One deployment, one brand. Rebranding is an .env change and a rebuild,
| not a fork.
*/

return [

    'name' => env('BRAND_NAME', env('APP_NAME', 'Storefront')),
    'legal_name' => env('BRAND_LEGAL_NAME', env('BRAND_NAME', 'Storefront')),
    'tagline' => env('BRAND_TAGLINE', 'Cash on delivery, everywhere we ship'),
    'domain' => env('BRAND_DOMAIN', 'localhost'),

    /*
    | Theme
    |--------------------------------------------------------------------------
    | These become CSS custom properties on <html>, so the storefront and the
    | Filament panel resolve to the same palette from one source. The accent
    | is reserved for price, discount and the primary buy action.
    */
    'theme' => [
        'accent' => env('BRAND_ACCENT', '#f0501e'),
        'accent_dark' => env('BRAND_ACCENT_DARK', '#d43f11'),
        'success' => env('BRAND_SUCCESS', '#12854c'),
        'radius' => env('BRAND_RADIUS', '10px'),
        'default_mode' => env('BRAND_DEFAULT_MODE', 'light'),
    ],

    /*
    | Assets. Paths are relative to the public disk; null falls back to the
    | lettermark built from the brand name, so a new deployment is never
    | blocked on waiting for a logo file.
    */
    'logo' => [
        'light' => env('BRAND_LOGO_LIGHT'),
        'dark' => env('BRAND_LOGO_DARK'),
        'favicon' => env('BRAND_FAVICON'),
    ],

    'support' => [
        'whatsapp' => env('BRAND_SUPPORT_WHATSAPP'),
        'phone' => env('BRAND_SUPPORT_PHONE'),
        'email' => env('BRAND_SUPPORT_EMAIL'),
        'hours' => env('BRAND_SUPPORT_HOURS', '9am – 11pm, every day'),
    ],

    /*
    | Feature switches
    |--------------------------------------------------------------------------
    | Not every deployment sells the same way. A store with prepaid options
    | turns COD-only off; a single-brand store hides brand facets entirely.
    */
    'features' => [
        'cod_only' => (bool) env('BRAND_COD_ONLY', true),
        'wishlist' => (bool) env('BRAND_WISHLIST', true),
        'reviews' => (bool) env('BRAND_REVIEWS', true),
        'brand_facet' => (bool) env('BRAND_SHOW_BRAND_FACET', true),
        'multi_currency' => (bool) env('BRAND_MULTI_CURRENCY', false),
    ],
];
