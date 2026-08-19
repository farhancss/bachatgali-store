<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\AttributeType;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property AttributeType $type
 * @property bool $is_filterable
 * @property bool $is_variant_defining
 * @property int $position
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }

    /**
     * An attribute may only define variants if its type has a closed set of
     * values — you cannot pick a SKU from a free-text box.
     */
    public function canDefineVariants(): bool
    {
        return $this->is_variant_defining && $this->type->canDefineVariants();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFilterable(Builder $query): Builder
    {
        return $query->where('is_filterable', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'is_filterable' => 'boolean',
            'is_variant_defining' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** @return AttributeFactory */
    protected static function newFactory(): Factory
    {
        return AttributeFactory::new();
    }
}
