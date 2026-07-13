<?php

namespace Database\Factories;

use App\Models\SickLeaveGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SickLeaveGroup>
 */
class SickLeaveGroupFactory extends Factory
{
    protected $model = SickLeaveGroup::class;

    public function definition(): array
    {
        $startDate = now()->toDateString();

        return [
            'group_key' => 'sick-'.now()->format('YmdHis').'-'.Str::lower(Str::random(10)),
            'user_id' => User::factory(),
            'start_date' => $startDate,
            'end_date' => $startDate,
            'note' => null,
            'recorded_by' => User::factory(),
            'meta' => [],
        ];
    }
}
