<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Queries\ProductListQuery;
use App\Http\Requests\Catalog\ProductListRequest;
use Illuminate\Contracts\View\View;

final class CategoryController
{
    public function __invoke(ProductListRequest $request, Category $category): View
    {
        $filters = $request->toFilters()->withCategory($category->slug);

        return view('catalog.category', [
            'categories' => Category::query()->active()->roots()->orderBy('position')->get(),
            'category' => $category,
            'children' => $category->children()->where('is_active', true)->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
            'filters' => $filters,
            'products' => new ProductListQuery($filters)->paginate(24),
        ]);
    }
}
