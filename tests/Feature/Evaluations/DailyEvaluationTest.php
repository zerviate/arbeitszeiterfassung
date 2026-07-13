<?php

namespace Tests\Feature\Evaluations;

use App\Models\AbsenceRecord;
use App\Models\Contract;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_open_own_day_evaluation(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'is_active' => true,
        ]);

        WorkSession::query()->create([
            'user_id' => $employee->id,
            'work_date' => '2026-05-04',
            'started_at' => '2026-05-04 08:00:00',
            'ended_at' => '2026-05-04 15:30:00',
            'gross_minutes' => 450,
            'status' => WorkSession::STATUS_CLOSED,
        ]);

        $this->actingAs($employee)
            ->get(route('evaluations.day', [$employee, '2026-05-04']))
            ->assertOk();

        $evaluationExists = DailyWorktimeEvaluation::query()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', '2026-05-04')
            ->where('target_minutes', 480)
            ->where('actual_minutes', 450)
            ->where('day_status', 'minor_under_target')
            ->where('traffic_light', 'yellow')
            ->where('balance_minutes', -30)
            ->exists();

        $this->assertTrue($evaluationExists);
    }

    public function test_vacation_day_is_counted_as_fulfilled_target(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'is_active' => true,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_VACATION,
            'absence_date' => '2026-05-05',
            'source' => AbsenceRecord::SOURCE_REQUEST_APPROVED,
            'recorded_by' => $employee->id,
        ]);

        $this->actingAs($employee)
            ->get(route('evaluations.day', [$employee, '2026-05-05']))
            ->assertOk();

        $evaluationExists = DailyWorktimeEvaluation::query()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', '2026-05-05')
            ->where('vacation_minutes', 480)
            ->where('day_status', 'vacation')
            ->where('traffic_light', 'green')
            ->where('balance_minutes', 0)
            ->exists();

        $this->assertTrue($evaluationExists);
    }

    public function test_sick_leave_day_is_counted_as_fulfilled_target(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'is_active' => true,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-05-06',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'recorded_by' => $employee->id,
        ]);

        $this->actingAs($employee)
            ->get(route('evaluations.day', [$employee, '2026-05-06']))
            ->assertOk();

        $evaluationExists = DailyWorktimeEvaluation::query()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', '2026-05-06')
            ->where('sick_leave_minutes', 480)
            ->where('day_status', 'sick_leave')
            ->where('traffic_light', 'green')
            ->where('balance_minutes', 0)
            ->exists();

        $this->assertTrue($evaluationExists);
    }

    public function test_manager_can_view_team_evaluation_but_not_foreign_user(): void
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
        ]);

        Contract::factory()->create([
            'user_id' => $foreignUser->id,
            'valid_from' => '2026-01-01',
        ]);

        $this->actingAs($manager)
            ->get(route('evaluations.day', [$teamMember, '2026-05-07']))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('evaluations.day', [$foreignUser, '2026-05-07']))
            ->assertForbidden();
    }
}
