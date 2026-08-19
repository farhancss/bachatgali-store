<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(2), '.');

        return [
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function childOf(Category $parent): self
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
