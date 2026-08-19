<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\AttributeType;
use App\Domain\Catalog\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Attribute> */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(1), '.');

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'type' => AttributeType::Select,
            'is_filterable' => true,
            'is_variant_defining' => false,
            'position' => 0,
        ];
    }

    public function variantDefining(): self
    {
        return $this->state(fn (): array => [
            'type' => AttributeType::Select,
            'is_variant_defining' => true,
        ]);
    }

    public function ofType(AttributeType $type): self
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
