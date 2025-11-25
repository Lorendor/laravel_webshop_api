<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        $fileTypes = ['PSD', 'AI', 'JPG', 'PNG', 'SVG'];
        $categories = ['Graphics', 'Icons', 'Templates', 'Illustrations', 'Photos'];
        $licenseTypes = ['standard', 'extended', 'commercial'];

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 100),
            'file_path' => 'products/' . fake()->uuid() . '.' . strtolower(fake()->randomElement($fileTypes)),
            'preview_image' => 'previews/' . fake()->uuid() . '.jpg',
            'file_type' => fake()->randomElement($fileTypes),
            'file_size' => fake()->numberBetween(100000, 10000000), // 100KB to 10MB
            'tags' => fake()->words(5),
            'category' => fake()->randomElement($categories),
            'license_type' => fake()->randomElement($licenseTypes),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
