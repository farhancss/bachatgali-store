<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pricing\Enums\VoucherType;
use App\Domain\Pricing\Models\Voucher;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Voucher> */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'description' => '10% off your order',
            'type' => VoucherType::PercentageOff,
            'percentage' => 10,
            'amount' => null,
            'maximum_discount' => null,
            'minimum_spend' => Money::zero(),
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'times_used' => 0,
        ];
    }

    public function percentage(int $percent, ?int $capRupees = null): self
    {
        return $this->state(fn (): array => [
            'type' => VoucherType::PercentageOff,
            'percentage' => $percent,
            'maximum_discount' => $capRupees === null ? null : Money::fromRupees($capRupees),
        ]);
    }

    public function fixed(int $rupees): self
    {
        return $this->state(fn (): array => [
            'type' => VoucherType::FixedAmountOff,
            'percentage' => null,
            'amount' => Money::fromRupees($rupees),
        ]);
    }

    public function freeDelivery(): self
    {
        return $this->state(fn (): array => [
            'type' => VoucherType::FreeDelivery,
            'percentage' => null,
        ]);
    }

    public function minimumSpend(int $rupees): self
    {
        return $this->state(fn (): array => ['minimum_spend' => Money::fromRupees($rupees)]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function exhausted(): self
    {
        return $this->state(fn (): array => ['usage_limit' => 5, 'times_used' => 5]);
    }
}
