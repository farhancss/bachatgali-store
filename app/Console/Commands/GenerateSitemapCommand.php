<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Writes public/sitemap.xml from the catalog.
 *
 * Generated on a schedule rather than rendered per request: a crawler hitting
 * a live-rendered sitemap on a large catalog is a self-inflicted load spike,
 * and the file is trivially served by the web server or the CDN.
 *
 * Only Active products appear. A draft in the sitemap is an invitation for
 * Google to crawl a 404.
 */
final class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write public/sitemap.xml from the published catalog';

    public function handle(): int
    {
        $sitemap = Sitemap::create()
            ->add(
                Url::create(route('home'))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(1.0),
            );

        $categories = 0;

        Category::query()
            ->where('is_active', true)
            ->orderBy('path')
            ->each(function (Category $category) use ($sitemap, &$categories): void {
                $sitemap->add(
                    Url::create(route('category', $category->slug))
                        ->setLastModificationDate($category->updated_at ?? now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                        // Shallower categories matter more; depth 0 gets 0.9,
                        // and it tapers rather than treating a leaf as equal.
                        ->setPriority(max(0.4, 0.9 - ($category->depth * 0.1))),
                );

                $categories++;
            });

        $products = 0;

        Product::query()
            ->active()
            ->orderBy('id')
            ->each(function (Product $product) use ($sitemap, &$products): void {
                $sitemap->add(
                    Url::create(route('product', $product->slug))
                        ->setLastModificationDate($product->updated_at ?? now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.8),
                );

                $products++;
            });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info(sprintf(
            'sitemap.xml written: 1 home, %d categories, %d products.',
            $categories,
            $products,
        ));

        return self::SUCCESS;
    }
}
