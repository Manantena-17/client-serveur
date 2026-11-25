<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(1, 10);

        return [
            'title'            => fake()->sentence(3),
            'author_id' => Author::factory(),
            'isbn'             => fake()->unique()->isbn13(),
            'publication_year' => fake()->numberBetween(1980, 2025),
            'total_copies'     => $total,
            'available_copies' => $total,
            'category_id'      => Category::factory(),
        ];
    }

    // State: a book with no copy available
    public function unavailable(): static
    {
        return $this->state(fn () => ['available_copies' => 0]);
    }
}
