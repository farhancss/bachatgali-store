<div class="ann">
    <div class="wrap">
        <span><strong>Cash on delivery</strong> — pay only when your order arrives</span>
        <span>Free delivery over {{ \App\Domain\Shared\ValueObjects\Money::fromPaisa(config('bachatgali.delivery.free_threshold'))->format() }}</span>
        <span>7-day returns</span>
    </div>
</div>
