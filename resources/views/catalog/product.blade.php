@extends('layouts.storefront')

@section('title', $product->name . ' — ' . config('brand.name'))
@section('description', $product->short_description ?? $product->name)
@section('canonical', route('product', $product))

@php
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $product->short_description,
        'sku' => $variant?->sku,
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => array_filter([
            '@type' => 'Offer',
            'url' => route('product', $product),
            'priceCurrency' => config('bachatgali.currency.code'),
            // Schema.org wants a decimal string; paisa is divided exactly once,
            // here at the boundary, and never inside the domain.
            'price' => $variant ? number_format($variant->price->paisa / 100, 2, '.', '') : null,
            'availability' => $variant?->stockState()->isPurchasable()
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
        ], static fn ($v) => $v !== null),
    ], static fn ($v) => $v !== null);
@endphp

@push('schema')
    {{-- JSON-LD: the single most valuable thing on this page for search. --}}
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@section('content')
    <div class="wrap">
        <nav class="crumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            @foreach ($product->categories as $category)
                <span>/</span><a href="{{ route('category', $category) }}">{{ $category->name }}</a>
            @endforeach
            <span>/</span><span>{{ $product->name }}</span>
        </nav>

        <div class="pdp">
            <div class="gal">
                <div class="gmain">
                    <img src="{{ $product->getFirstMediaUrl('gallery') ?: trim($__env->make('components.catalog.placeholder-image', ['size' => 800])->render()) }}"
                         alt="{{ $product->name }}" width="800" height="800" class="ld">
                </div>
            </div>

            <div class="pinfo">
                <h1>{{ $product->name }}</h1>

                <div class="meta">
                    @if ($product->brand)
                        <a href="{{ route('search', ['brand' => [$product->brand->slug]]) }}">{{ $product->brand->name }}</a>
                        <span class="dot"></span>
                    @endif
                    <span>SKU {{ $variant?->sku }}</span>
                </div>

                @if ($product->short_description)
                    <p class="muted">{{ $product->short_description }}</p>
                @endif

                @if ($product->description)
                    <div class="sec">{!! nl2br(e($product->description)) !!}</div>
                @endif

                @if ($product->type->allowsMultipleVariants() && $product->variants->count() > 1)
                    <div class="opts">
                        <div class="fl"><label>Choose an option</label></div>
                        @foreach ($product->variants as $option)
                            <a class="opt @if ($option->is($variant)) on @endif"
                               href="{{ route('product', $product) }}?variant={{ $option->id }}">
                                {{ $option->name ?? $option->sku }}
                                <span class="tiny">{{ $option->price->format(config('bachatgali.currency.symbol')) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="buybox">
                <div class="prb">
                    <span class="now">{{ $variant?->price->format(config('bachatgali.currency.symbol')) }}</span>
                    @if ($variant?->isOnSale())
                        <s class="old">{{ $variant->compare_at_price?->format(config('bachatgali.currency.symbol')) }}</s>
                        <span class="sv">Save {{ $variant->savings()->format(config('bachatgali.currency.symbol')) }}</span>
                    @endif
                </div>

                <p class="{{ $variant?->stockState()->isPurchasable() ? 'ok' : 'muted' }}">
                    {{ $variant?->stockState()->label() }}
                </p>

                <button class="btn btn-a btn-lg" @disabled(! $variant?->stockState()->isPurchasable())>
                    {{ $variant?->stockState()->isPurchasable() ? 'Add to cart' : 'Out of stock' }}
                </button>

                <div class="codbox">
                    <strong>Cash on delivery</strong>
                    <p class="tiny">Pay the rider when your order arrives. No card needed.</p>
                </div>
            </aside>
        </div>

        @if ($related->isNotEmpty())
            <section class="sec">
                <div class="sh"><h2>You might also like</h2></div>
                <div class="grid g6">
                    @foreach ($related as $item)
                        <x-catalog.product-card :product="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
