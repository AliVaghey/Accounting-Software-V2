<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(1000, 1000000),
            'contact_id' => null,
            'description' => fake()->sentence(4),
            'is_income' => false,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => ['is_income' => true]);
    }
}
