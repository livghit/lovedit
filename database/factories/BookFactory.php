<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'description' => $this->faker->paragraphs(3, true),
            'isbn' => $this->faker->isbn13(),
            'cover_url' => $this->faker->imageUrl(300, 450),
            'published_year' => $this->faker->year(),
            'publisher' => $this->faker->company(),
            'search_count' => 0,
        ];
    }
}
