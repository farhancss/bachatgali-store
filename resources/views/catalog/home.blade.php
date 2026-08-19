@extends('layouts.storefront')

@section('title', config('brand.name') . ' — ' . config('brand.tagline'))
@section('description', config('brand.tagline'))

@section('content')
    <div class="wrap">
        @if ($categories->isNotEmpty())
            <section class="sec">
                <div class="rail">
                    @foreach ($categories as $category)
                        <a class="rcat" href="{{ route('category', $category) }}">
                            <span class="ln-i">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/></svg>
                            </span>
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($onSale->isNotEmpty())
            <section class="sec">
                <div class="sh"><h2>On sale now</h2><a class="tiny" href="{{ route('search', ['on_sale' => 1]) }}">See all</a></div>
                <div class="grid g5">
                    @foreach ($onSale as $product)
                        <x-catalog.product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        <section class="sec">
            <div class="sh"><h2>Just for you</h2><a class="tiny" href="{{ route('search') }}">Browse everything</a></div>
            @if ($featured->isEmpty())
                <p class="muted">No products yet. Run <code>php artisan migrate:fresh --seed</code> to load the demo catalog.</p>
            @else
                <div class="grid g5">
                    @foreach ($featured as $product)
                        <x-catalog.product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
