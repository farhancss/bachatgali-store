@php
    $freeFrom = \App\Domain\Shared\ValueObjects\Money::fromPaisa(
        config('bachatgali.delivery.free_threshold')
    )->format(config('bachatgali.currency.symbol'));
@endphp

<div class="ann">
    <div class="wrap">
        <span><strong>Cash on delivery</strong> — pay only when your order arrives</span>
        <span>Free delivery over {{ $freeFrom }}</span>
        <span>7-day returns</span>
    </div>
</div>
