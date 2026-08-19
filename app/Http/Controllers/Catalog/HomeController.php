<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\DataObjects\ProductFilters;
use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Queries\ProductListQuery;
use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(): View
    {
        $featured = new ProductListQuery(
            new ProductFilters(sort: ProductSort::Relevance),
        )->builder()->limit(10)->get();

        $onSale = new ProductListQuery(
            new ProductFilters(onSaleOnly: true, sort: ProductSort::Discount),
        )->builder()->limit(5)->get();

        return view('catalog.home', [
            'categories' => Category::query()->active()->roots()->orderBy('position')->get(),
            'featured' => $featured,
            'onSale' => $onSale,
        ]);
    }
}
