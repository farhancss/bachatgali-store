<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\StockState;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\ValueObjects\Money;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The sellable unit: one SKU, one price, one stock figure.
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $barcode
 * @property string|null $name
 * @property Money $price
 * @property Money|null $compare_at_price
 * @property Money|null $cost
 * @property int $stock_quantity
 * @property int $low_stock_threshold
 * @property bool $backorder_allowed
 * @property bool $is_pre_order
 * @property int $weight_grams
 * @property bool $is_default
 * @property int $position
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The axes that identify this SKU — size, colour. */
    /** @return BelongsToMany<AttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class);
    }

    /**
     * Availability is derived, never stored: a stored copy is one missed
     * write away from overselling.
     */
    public function stockState(): StockState
    {
        if ($this->is_pre_order) {
            return StockState::PreOrder;
        }

        return StockState::forQuantity(
            quantity: $this->stock_quantity,
            lowStockThreshold: $this->low_stock_threshold,
            backorderAllowed: $this->backorder_allowed,
        );
    }

    /** Is the compare-at price a genuine reduction rather than decoration? */
    public function isOnSale(): bool
    {
        return $this->compare_at_price instanceof Money
            && $this->compare_at_price->isGreaterThan($this->price);
    }

    public function savings(): Money
    {
        return $this->isOnSale() && $this->compare_at_price instanceof Money
            ? $this->compare_at_price->minusClamped($this->price)
            : Money::zero();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'compare_at_price' => MoneyCast::class,
            'cost' => MoneyCast::class,
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'backorder_allowed' => 'boolean',
            'is_pre_order' => 'boolean',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** @return ProductVariantFactory */
    protected static function newFactory(): Factory
    {
        return ProductVariantFactory::new();
    }
}
