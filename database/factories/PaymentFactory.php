<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'amount' => 99000,
            'attachment_path' => 'payment-proofs/'.fake()->uuid().'.png',
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
