@props(['product'])

@php
    /** @var \App\Domain\Catalog\Models\Product $product */
    $variant = $product->defaultVariant();
    $state = $variant?->stockState();
    // Integer arithmetic only, like every other money calculation here.
    $discount = $variant && $variant->isOnSale() && $variant->compare_at_price
        ? intdiv($variant->savings()->paisa * 100, max(1, $variant->compare_at_price->paisa))
        : null;
    $freeDeliveryFrom = \App\Domain\Shared\ValueObjects\Money::fromPaisa(
        (int) config('bachatgali.delivery.free_threshold'),
    );
@endphp

<article class="p">
    <a href="{{ route('product', $product) }}" class="p-img">
        @if ($discount !== null)
            <span class="off">−{{ $discount }}%</span>
        @endif
        @if ($state?->isScarce())
            <span class="flag">Almost gone</span>
        @endif
        <img src="{{ $product->getFirstMediaUrl('gallery', 'card') ?: trim($__env->make('components.catalog.placeholder-image', ['size' => 600])->render()) }}"
             alt="{{ $product->name }}" loading="lazy" width="600" height="600" class="ld">
    </a>
    <div class="p-b">
        <a href="{{ route('product', $product) }}" class="p-t">{{ $product->name }}</a>

        <div class="pr">
            <b>{{ $variant?->price->format(config('bachatgali.currency.symbol')) }}</b>
            @if ($variant?->isOnSale())
                <s>{{ $variant->compare_at_price?->format(config('bachatgali.currency.symbol')) }}</s>
            @endif
        </div>

        <div class="meta">
            @if ($product->brand)
                {{ $product->brand->name }}<span class="dot"></span>
            @endif
            <span @class(['muted' => ! $state?->isPurchasable()])>{{ $state?->label() }}</span>
        </div>

        <div class="badges">
            @if ($variant?->price->isGreaterThanOrEqualTo($freeDeliveryFrom))
                <span class="bd">Free delivery</span>
            @endif
            <span class="bd cod">COD</span>
        </div>
    </div>
</article>
