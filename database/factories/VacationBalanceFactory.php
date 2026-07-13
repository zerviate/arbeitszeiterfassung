<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VacationBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => (int) now()->format('Y'),
            'annual_entitlement_days' => 30,
            'carryover_days' => 0,
            'manual_adjustment_days' => 0,
            'note' => null,
            'created_by' => null,
            'meta' => [],
        ];
    }
}
