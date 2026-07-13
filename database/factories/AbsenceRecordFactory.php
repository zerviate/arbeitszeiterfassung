<?php

namespace Database\Factories;

use App\Models\AbsenceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceRecord>
 */
class AbsenceRecordFactory extends Factory
{
    protected $model = AbsenceRecord::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => AbsenceRecord::TYPE_VACATION,
            'absence_date' => now()->toDateString(),
            'source' => AbsenceRecord::SOURCE_REQUEST_APPROVED,
            'note' => null,
            'reference_group' => null,
            'sick_leave_group_id' => null,
            'absence_request_id' => null,
            'recorded_by' => User::factory(),
            'meta' => [],
        ];
    }
}
