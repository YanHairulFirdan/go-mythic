<?php

namespace Database\Factories;

use App\Models\CapitalEntry;
use App\Models\CapitalTopup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CapitalTopup>
 */
class CapitalTopupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'capital_entry_id' => CapitalEntry::factory(),
            'amount' => $this->faker->numberBetween(250_000, 5_000_000),
            'changed_by' => fn (array $attributes) => CapitalEntry::find($attributes['capital_entry_id'])?->created_by
                ?? User::factory()->create()->id,
            'changed_at' => Carbon::now(),
            'extended_end_date' => null,
        ];
    }
}
