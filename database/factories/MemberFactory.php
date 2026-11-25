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
            'name'                => fake()->name(),
            'email'               => fake()->unique()->safeEmail(),
            'password'            => 'password123',
            'type'                => fake()->randomElement(['student', 'teacher']),
            'subscription_status' => fake()->randomElement(['free', 'premium']),
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
    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }
}
