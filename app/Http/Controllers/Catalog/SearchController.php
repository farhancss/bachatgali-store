<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\SearchProducts;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Http\Requests\Catalog\ProductListRequest;
use Illuminate\Contracts\View\View;

final readonly class SearchController
{
    public function __construct(private SearchProducts $search) {}

    public function __invoke(ProductListRequest $request): View
    {
        $filters = $request->toFilters();

        return view('catalog.search', [
            'categories' => Category::query()->active()->roots()->orderBy('position')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
            'filters' => $filters,
            'products' => $this->search->handle($filters, (int) $request->integer('page', 1)),
        ]);
    }
}
