<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'  => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'type'  => fake()->randomElement(['student', 'teacher']),
        ];
    }

    public function student(): static
    {
        return $this->state(fn () => ['type' => 'student']);
    }

    public function teacher(): static
    {
        return $this->state(fn () => ['type' => 'teacher']);
    }
}
