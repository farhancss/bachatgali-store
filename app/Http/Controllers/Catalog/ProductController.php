<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Queries\RelatedProductsQuery;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductController
{
    public function __invoke(Product $product): View
    {
        // A draft or archived product must 404 rather than render — it may be
        // linked from a stale sitemap or an old campaign.
        if (! $product->status->isVisibleToCustomers()) {
            throw new NotFoundHttpException;
        }

        $product->load(['brand', 'variants.attributeValues.attribute', 'categories']);

        return view('catalog.product', [
            'categories' => Category::query()->active()->roots()->orderBy('position')->get(),
            'product' => $product,
            'variant' => $product->defaultVariant(),
            'related' => new RelatedProductsQuery($product)->get(6),
        ]);
    }
}
