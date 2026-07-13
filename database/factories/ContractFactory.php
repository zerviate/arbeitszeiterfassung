<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weekly_minutes' => 2400,
            'workdays_pattern' => [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => false,
                'sunday' => false,
            ],
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => null,
            'is_active' => true,
            'created_by' => null,
            'meta' => [],
        ];
    }
}
