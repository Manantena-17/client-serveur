<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    public function definition(): array
    {
        $borrowed = now()->subDays(fake()->numberBetween(0, 10));

        return [
            'member_id'   => Member::factory(),
            'book_id'     => Book::factory(),
            'borrowed_at' => $borrowed->toDateString(),
            'due_at'      => $borrowed->copy()->addDays(14)->toDateString(),
            'returned_at' => null,
        ];
    }

    // State: a loan already returned
    public function returned(): static
    {
        return $this->state(fn () => ['returned_at' => now()->toDateString()]);
    }

    // State: a loan past its due date
    public function overdue(): static
    {
        return $this->state(fn () => [
            'borrowed_at' => now()->subDays(30)->toDateString(),
            'due_at'      => now()->subDays(10)->toDateString(),
            'returned_at' => null,
        ]);
    }
}
