<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Shared\ValueObjects\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Identity and copy. Price and stock live on ProductVariant — always, for
 * every product type — so nothing downstream has to ask what type a product
 * is before it can find out what it costs.
 *
 * @property int $id
 * @property int|null $brand_id
 * @property ProductType $type
 * @property ProductStatus $status
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property bool $is_featured
 * @property int $position
 * @property Carbon|null $published_at
 */
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('position');
    }

    /** Spec-sheet attributes, as opposed to the axes that define a variant. */
    /** @return BelongsToMany<AttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class);
    }

    /**
     * The variant a product page opens on.
     *
     * Reads from the already-loaded collection rather than querying, because
     * Model::preventLazyLoading() is on outside production — callers eager
     * load `variants` and this stays free.
     */
    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('is_default', true)
            ?? $this->variants->first();
    }

    /** The "from" price shown on a listing card. */
    public function lowestPrice(): ?Money
    {
        $prices = $this->variants->map(static fn (ProductVariant $v): int => $v->price->paisa);

        return $prices->isEmpty() ? null : Money::fromPaisa((int) $prices->min());
    }

    public function isPurchasable(): bool
    {
        return $this->status->isVisibleToCustomers()
            && $this->variants->contains(static fn (ProductVariant $v): bool => $v->stockState()->isPurchasable());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active->value);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->nonQueued()->width(300)->height(300);
        $this->addMediaConversion('card')->width(600)->height(600);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The search document. Flattened on purpose — an engine cannot join, so
     * everything a facet or a result card needs is denormalised here.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['brand', 'variants', 'categories']);

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'brand' => $this->brand?->name,
            'brand_slug' => $this->brand?->slug,
            'categories' => $this->categories->pluck('name')->all(),
            'category_slugs' => $this->categories->pluck('slug')->all(),
            'skus' => $this->variants->pluck('sku')->all(),
            // Paisa, so the engine sorts and range-filters on an integer.
            'price' => $this->lowestPrice() instanceof Money ? $this->lowestPrice()->paisa : 0,
            'in_stock' => $this->variants->contains(
                static fn (ProductVariant $v): bool => $v->stockState()->isPurchasable(),
            ),
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->getTimestamp(),
        ];
    }

    /** Drafts and archived products must never appear in search results. */
    public function shouldBeSearchable(): bool
    {
        return $this->status->shouldBeSearchable();
    }

    public function searchableAs(): string
    {
        return 'products';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'position' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return ProductFactory */
    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}
