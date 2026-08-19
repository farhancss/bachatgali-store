<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A node in the category tree.
 *
 * `path` is a materialised list of ancestor ids ("/1/4/") maintained on save,
 * so "everything beneath this node" is one indexed prefix match instead of a
 * recursive query. `depth` is derived alongside it and exists so breadcrumbs
 * and menu levels never need to walk upwards.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $path
 * @property int $depth
 * @property bool $is_active
 * @property int $position
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $category->syncTreeColumns();
        });

        static::updated(function (self $category): void {
            if ($category->wasChanged('path')) {
                $category->repathDescendants();
            }
        });
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('position');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /** The prefix every descendant's `path` starts with. */
    public function descendantPathPrefix(): string
    {
        return $this->path.$this->id.'/';
    }

    /** @return array<int, int> Ancestor ids, outermost first. */
    public function ancestorIds(): array
    {
        return array_map(
            static fn (string $id): int => (int) $id,
            array_values(array_filter(explode('/', $this->path), static fn (string $s): bool => $s !== '')),
        );
    }

    /**
     * Every category beneath this one, at any depth, in one query.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDescendantsOf(Builder $query, self $category): Builder
    {
        return $query->where('path', 'like', $category->descendantPathPrefix().'%');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Derives `path` and `depth` from the parent. */
    private function syncTreeColumns(): void
    {
        $parent = $this->parent_id === null ? null : self::query()->find($this->parent_id);

        $this->path = $parent instanceof self ? $parent->descendantPathPrefix() : '/';
        $this->depth = $parent instanceof self ? $parent->depth + 1 : 0;
    }

    /**
     * Re-path the subtree after a move. Saving each child re-triggers this
     * hook, so an arbitrarily deep subtree settles without a recursive query.
     */
    private function repathDescendants(): void
    {
        $this->children()->get()->each(static function (self $child): void {
            $child->save();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'depth' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return CategoryFactory */
    protected static function newFactory(): Factory
    {
        return CategoryFactory::new();
    }
}
