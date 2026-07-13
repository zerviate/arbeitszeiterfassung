<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyWorktimeEvaluationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'work_date' => now()->toDateString(),
            'contract_id' => Contract::factory(),
            'is_scheduled_workday' => true,
            'is_holiday' => false,
            'holiday_name' => null,
            'target_minutes' => 480,
            'actual_minutes' => 480,
            'vacation_minutes' => 0,
            'sick_leave_minutes' => 0,
            'balance_minutes' => 0,
            'day_status' => 'fulfilled',
            'traffic_light' => 'green',
            'flags' => [],
        ];
    }
}
