<?php

namespace Database\Factories;

use App\Models\CapitalEntry;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CapitalEntry>
 */
class CapitalEntryFactory extends Factory
{
    public function definition(): array
    {
        $start = Carbon::instance($this->faker->dateTimeBetween('-10 days', 'now'))->startOfDay();

        return [
            'company_id' => Company::factory(),
            'initial_amount' => $this->faker->numberBetween(1_000_000, 10_000_000),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(29)->toDateString(),
            'created_by' => fn (array $attributes) => User::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
        ];
    }
}
