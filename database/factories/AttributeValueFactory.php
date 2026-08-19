<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttributeValue> */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attribute_id' => Attribute::factory(),
            'value' => fake()->unique()->word(),
            'label' => null,
            'position' => 0,
        ];
    }

    public function for_(Attribute $attribute, string $value): self
    {
        return $this->state(fn (): array => [
            'attribute_id' => $attribute->id,
            'value' => $value,
        ]);
    }
}
