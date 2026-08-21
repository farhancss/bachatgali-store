<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Domain\Pricing\Enums\VoucherType;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\ValueObjects\Money;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property VoucherType $type
 * @property int|null $percentage
 * @property Money|null $amount
 * @property Money|null $maximum_discount
 * @property Money $minimum_spend
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property int|null $usage_limit
 * @property int $times_used
 */
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Codes are matched case-insensitively — customers type them off a
     * banner, and rejecting "save10" because the record says "SAVE10" is a
     * support ticket, not a security boundary.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->whereRaw('upper(code) = ?', [mb_strtoupper(trim($code))]);
    }

    public function hasUsesLeft(): bool
    {
        return $this->usage_limit === null || $this->times_used < $this->usage_limit;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'percentage' => 'integer',
            'amount' => MoneyCast::class,
            'maximum_discount' => MoneyCast::class,
            'minimum_spend' => MoneyCast::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'times_used' => 'integer',
        ];
    }

    /** @return VoucherFactory */
    protected static function newFactory(): Factory
    {
        return VoucherFactory::new();
    }
}
