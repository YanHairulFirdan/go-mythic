<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'has_access_to_system' => false,
            'status' => 'active',
        ];
    }

    public function withAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_access_to_system' => true,
        ]);
    }
}
