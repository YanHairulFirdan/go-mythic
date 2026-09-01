<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['income', 'expense']);

        return [
            'company_id' => Company::factory(),
            'type' => $type,
            'category_id' => fn (array $attributes) => TransactionCategory::factory()->create([
                'company_id' => $attributes['company_id'],
                'type' => $attributes['type'],
            ])->id,
            'customer_id' => null,
            'invoice_id' => null,
            'employee_id' => null,
            'created_by' => fn (array $attributes) => User::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'updated_by' => null,
            'amount' => fake()->numberBetween(10_000, 5_000_000),
            'transaction_date' => now()->toDateString(),
            'payment_method' => fake()->randomElement(['cash', 'transfer', 'qris', 'other']),
            'notes' => fake()->optional()->sentence(),
            'attachment_path' => null,
        ];
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
