<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cart\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'session_id' => Str::random(40),
            'user_id' => null,
            'voucher_code' => null,
            'last_activity_at' => now(),
        ];
    }
}
