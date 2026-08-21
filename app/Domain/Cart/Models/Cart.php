<?php

declare(strict_types=1);

namespace App\Domain\Cart\Models;

use App\Domain\Shared\ValueObjects\Money;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $session_id
 * @property int|null $user_id
 * @property string|null $voucher_code
 * @property Carbon|null $last_activity_at
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Lines in the shape CalculateCartTotals expects.
     *
     * @return list<array{unit_price: Money, quantity: int}>
     */
    public function pricingLines(): array
    {
        // array_values, not Collection::values()->all(): PHPStan can prove
        // the former produces a list, which is what the calculator's
        // signature requires.
        return array_values(array_map(
            static fn (CartItem $item): array => [
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
            ],
            $this->items->all(),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function itemCount(): int
    {
        // array_sum over typed ints rather than Collection::sum(), which
        // level 9 sees as mixed.
        return array_sum(array_map(
            static fn (CartItem $item): int => $item->quantity,
            $this->items->all(),
        ));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return CartFactory */
    protected static function newFactory(): Factory
    {
        return CartFactory::new();
    }
}
