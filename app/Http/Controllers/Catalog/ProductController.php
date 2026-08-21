<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Queries\RelatedProductsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductController
{
    public function __invoke(Request $request, Product $product): View
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
            'variant' => $this->selectedVariant($request, $product),
            'related' => new RelatedProductsQuery($product)->get(6),
        ]);
    }

    /**
     * The variant the page opens on.
     *
     * The requested id must belong to THIS product. Without that check a
     * crafted ?variant= would render another product's price and SKU under
     * this product's name — and the JSON-LD would publish it to Google.
     */
    private function selectedVariant(Request $request, Product $product): ?ProductVariant
    {
        $requested = $request->integer('variant');

        if ($requested > 0) {
            $match = $product->variants->firstWhere('id', $requested);

            if ($match instanceof ProductVariant) {
                return $match;
            }
        }

        return $product->defaultVariant();
    }
}
