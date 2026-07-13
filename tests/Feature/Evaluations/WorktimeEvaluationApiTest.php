<?php

namespace Tests\Feature\Evaluations;

use App\Models\Contract;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorktimeEvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_read_own_day_evaluation_via_api(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        WorkSession::query()->create([
            'user_id' => $employee->id,
            'work_date' => '2026-05-12',
            'started_at' => '2026-05-12 08:00:00',
            'ended_at' => '2026-05-12 16:00:00',
            'gross_minutes' => 480,
            'status' => WorkSession::STATUS_CLOSED,
        ]);

        $this->actingAs($employee)
            ->getJson('/api/evaluations/day/2026-05-12')
            ->assertOk()
            ->assertJsonPath('data.user.id', $employee->id)
            ->assertJsonPath('data.evaluation.target_minutes', 480)
            ->assertJsonPath('data.evaluation.actual_minutes', 480)
            ->assertJsonPath('data.evaluation.day_status', 'fulfilled')
            ->assertJsonPath('data.evaluation.traffic_light', 'green');
    }

    public function test_manager_can_read_team_week_but_not_foreign_week(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $teamMember = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
        ]);

        $foreignUser = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $teamMember->id,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        Contract::factory()->create([
            'user_id' => $foreignUser->id,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson('/api/evaluations/week/2026-05-13?user_id='.$teamMember->id)
            ->assertOk()
            ->assertJsonPath('data.user.id', $teamMember->id);

        $this->actingAs($manager)
            ->getJson('/api/evaluations/week/2026-05-13?user_id='.$foreignUser->id)
            ->assertForbidden();
    }
}
