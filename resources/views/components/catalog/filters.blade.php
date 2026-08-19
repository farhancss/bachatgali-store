@props(['brands', 'filters', 'action'])

{{--
    Facets are a plain GET form: every filter combination is a real URL, which
    is what makes the listing page shareable and crawlable (ADR-0003).
--}}
<form class="fbox" method="get" action="{{ $action }}">
    @if ($filters->search)
        <input type="hidden" name="q" value="{{ $filters->search }}">
    @endif
    <input type="hidden" name="sort" value="{{ $filters->sort->value }}">

    <div class="fg">
        <div class="fl"><label>Price (Rs.)</label></div>
        <div class="f2">
            <input class="in" type="number" name="min" min="0" placeholder="Min"
                   value="{{ $filters->minPrice ? intdiv($filters->minPrice->paisa, 100) : '' }}">
            <input class="in" type="number" name="max" min="0" placeholder="Max"
                   value="{{ $filters->maxPrice ? intdiv($filters->maxPrice->paisa, 100) : '' }}">
        </div>
    </div>

    @if ($brands->isNotEmpty())
        <div class="fg">
            <div class="fl"><label>Brand</label></div>
            @foreach ($brands as $brand)
                <label class="opt">
                    <input type="checkbox" name="brand[]" value="{{ $brand->slug }}"
                           @checked(in_array($brand->slug, $filters->brandSlugs, true))>
                    {{ $brand->name }}
                </label>
            @endforeach
        </div>
    @endif

    <div class="fg">
        <div class="fl"><label>Availability</label></div>
        <label class="opt">
            <input type="checkbox" name="in_stock" value="1" @checked($filters->inStockOnly)> In stock only
        </label>
        <label class="opt">
            <input type="checkbox" name="on_sale" value="1" @checked($filters->onSaleOnly)> On sale
        </label>
    </div>

    <button class="btn btn-a" type="submit">Apply filters</button>
    @unless ($filters->isEmpty())
        <a class="btn btn-o" href="{{ $action }}">Clear all ({{ $filters->activeCount() }})</a>
    @endunless
</form>
