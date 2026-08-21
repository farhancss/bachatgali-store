@extends('layouts.storefront')

@section('title', 'Your cart — ' . config('brand.name'))
@section('description', 'Review your order before checkout.')

@section('content')
    <div class="wrap">
        <div class="sec"><h1>Your cart</h1></div>

        @if (session('error'))
            <div class="card"><div class="card-b"><strong>{{ session('error') }}</strong></div></div>
        @endif

        @if (! $cart || $cart->items->isEmpty())
            <div class="card"><div class="card-b">
                <h3>Your cart is empty</h3>
                <p class="muted">Nothing here yet. Have a look around.</p>
                <a class="btn btn-a" href="{{ route('search') }}">Browse products</a>
            </div></div>
        @else
            <div class="plp">
                <div>
                    @foreach ($cart->items as $item)
                        @php
                            $variant = $item->variant;
                            $product = $variant?->product;
                        @endphp
                        <div class="card"><div class="card-b ln">
                            <div class="ln-m">
                                @if ($product)
                                    <a href="{{ route('product', $product) }}"><strong>{{ $product->name }}</strong></a>
                                @endif
                                <div class="tiny">
                                    {{ $variant?->name ?? $variant?->sku }}
                                    @if ($item->priceHasChanged())
                                        <span class="bdg">Price changed since you added this</span>
                                    @endif
                                </div>
                                <div class="pr"><b>{{ $item->unit_price->format(config('bachatgali.currency.symbol')) }}</b></div>
                            </div>

                            <form method="post" action="{{ route('cart.update', $item) }}" class="qty">
                                @csrf @method('PATCH')
                                <label class="tiny" for="q{{ $item->id }}">Qty</label>
                                <input class="in" id="q{{ $item->id }}" type="number" name="quantity"
                                       value="{{ $item->quantity }}" min="0" max="10" style="width:5rem">
                                <button class="btn btn-n btn-sm" type="submit">Update</button>
                            </form>

                            <form method="post" action="{{ route('cart.destroy', $item) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-o btn-sm" type="submit">Remove</button>
                            </form>

                            <div class="pr"><b>{{ $item->lineTotal()->format(config('bachatgali.currency.symbol')) }}</b></div>
                        </div></div>
                    @endforeach
                </div>

                <aside>
                    <div class="fbox">
                        <h3>Summary</h3>

                        <div class="orow"><span>Subtotal</span><b>{{ $totals->subtotal->format(config('bachatgali.currency.symbol')) }}</b></div>

                        @if ($totals->hasDiscount())
                            <div class="orow"><span>Discount{{ $totals->appliedVoucherCode ? ' (' . $totals->appliedVoucherCode . ')' : '' }}</span>
                                <b class="ok">− {{ $totals->discount->format(config('bachatgali.currency.symbol')) }}</b></div>
                        @endif

                        <div class="orow">
                            <span>Delivery</span>
                            <b>{{ $totals->freeDeliveryApplied ? 'Free' : $totals->deliveryFee->format(config('bachatgali.currency.symbol')) }}</b>
                        </div>

                        @unless ($totals->codHandlingFee->isZero())
                            <div class="orow"><span>COD handling</span><b>{{ $totals->codHandlingFee->format(config('bachatgali.currency.symbol')) }}</b></div>
                        @endunless

                        <div class="orow tl"><span><strong>Pay on delivery</strong></span>
                            <b class="now">{{ $totals->amountDueOnDelivery()->format(config('bachatgali.currency.symbol')) }}</b></div>

                        @if ($totals->appliedVoucherCode)
                            <form method="post" action="{{ route('cart.voucher.remove') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-o btn-sm" type="submit">Remove voucher</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('cart.voucher.apply') }}" class="f2">
                                @csrf
                                <input class="in" name="code" placeholder="Voucher code" maxlength="40">
                                <button class="btn btn-n" type="submit">Apply</button>
                            </form>
                        @endif

                        <div class="codbox">
                            <strong>Cash on delivery</strong>
                            <p class="tiny">Pay the rider when your order arrives. No card needed.</p>
                        </div>
                    </div>
                </aside>
            </div>
        @endif
    </div>
@endsection
