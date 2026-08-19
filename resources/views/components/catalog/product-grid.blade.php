@props(['products', 'brands', 'filters', 'action', 'heading' => null])

<div class="plp">
    <aside>
        <x-catalog.filters :brands="$brands" :filters="$filters" :action="$action" />
    </aside>

    <div>
        <div class="plbar">
            <div>
                @if ($heading)
                    <h1>{{ $heading }}</h1>
                @endif
                <span class="tiny">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</span>
            </div>

            <form class="sort" method="get" action="{{ $action }}">
                @foreach (request()->except(['sort', 'page']) as $key => $value)
                    @foreach (Arr::wrap($value) as $item)
                        <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $item }}">
                    @endforeach
                @endforeach
                <label class="tiny" for="sort">Sort</label>
                <select class="in" name="sort" id="sort" onchange="this.form.submit()">
                    @foreach (\App\Domain\Catalog\Enums\ProductSort::cases() as $option)
                        <option value="{{ $option->value }}" @selected($filters->sort === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($products->isEmpty())
            <div class="card"><div class="card-b">
                <h3>Nothing matched</h3>
                <p class="muted">Try removing a filter or widening the price range.</p>
                <a class="btn btn-o" href="{{ $action }}">Clear all filters</a>
            </div></div>
        @else
            <div class="grid g4">
                @foreach ($products as $product)
                    <x-catalog.product-card :product="$product" />
                @endforeach
            </div>

            <div class="sec">{{ $products->links() }}</div>
        @endif
    </div>
</div>
