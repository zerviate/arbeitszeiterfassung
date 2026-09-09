<?php

namespace Database\Factories;

use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceRequest>
 */
class AbsenceRequestFactory extends Factory
{
    protected $model = AbsenceRequest::class;

    public function definition(): array
    {
        $startDate = now()->startOfMonth()->addDays(10);
        $endDate = $startDate->copy()->addDays(2);

        return [
            'user_id' => User::factory(),
            'type' => AbsenceRequest::TYPE_VACATION,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days_requested' => 3,
            'reason' => fake()->sentence(),
            'status' => AbsenceRequest::STATUS_PENDING,
            'requested_by' => static fn (array $attributes) => $attributes['user_id'],
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }
}
