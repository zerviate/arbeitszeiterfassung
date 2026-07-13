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
        $startDate = now()->addDays(10)->toDateString();
        $endDate = now()->addDays(12)->toDateString();

        return [
            'user_id' => User::factory(),
            'type' => AbsenceRequest::TYPE_VACATION,
            'start_date' => $startDate,
            'end_date' => $endDate,
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
