<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionCategory>
 */
class TransactionCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['income', 'expense']),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function income(): static
    {
        return $this->state(fn (): array => ['type' => 'income']);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => ['type' => 'expense']);
    }
}
