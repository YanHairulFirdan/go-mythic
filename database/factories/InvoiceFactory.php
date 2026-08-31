<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'employee_id' => null,
            'created_by' => fn (array $attributes) => User::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
        ];
    }
}
