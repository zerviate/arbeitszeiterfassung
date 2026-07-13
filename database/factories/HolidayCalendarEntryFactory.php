<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayCalendarEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'holiday_date' => now()->toDateString(),
            'name' => 'Feiertag',
            'is_active' => true,
            'created_by' => User::factory(),
            'meta' => [],
        ];
    }
}
