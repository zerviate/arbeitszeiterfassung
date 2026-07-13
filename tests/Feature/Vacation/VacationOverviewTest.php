<?php

namespace Tests\Feature\Vacation;

use App\Models\AbsenceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_only_own_requests_in_overview(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'name' => 'Employee One',
        ]);

        $other = User::factory()->create([
            'role' => 'employee',
            'name' => 'Employee Other',
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $other->id,
            'requested_by' => $other->id,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($employee)
            ->get(route('vacations.index'))
            ->assertOk()
            ->assertSee('Employee One')
            ->assertDontSee('Employee Other');
    }

    public function test_manager_sees_team_and_own_requests_but_not_foreign_requests(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'name' => 'Manager User',
        ]);

        $teamMember = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
            'name' => 'Team Member',
        ]);

        $other = User::factory()->create([
            'role' => 'employee',
            'name' => 'Outside Member',
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $manager->id,
            'requested_by' => $manager->id,
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $teamMember->id,
            'requested_by' => $teamMember->id,
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $other->id,
            'requested_by' => $other->id,
        ]);

        $this->actingAs($manager)
            ->get(route('vacations.index'))
            ->assertOk()
            ->assertSee('Manager User')
            ->assertSee('Team Member')
            ->assertDontSee('Outside Member');
    }

    public function test_hr_and_auditor_see_all_requests_in_overview(): void
    {
        $employeeA = User::factory()->create([
            'role' => 'employee',
            'name' => 'Employee A',
        ]);

        $employeeB = User::factory()->create([
            'role' => 'employee',
            'name' => 'Employee B',
        ]);

        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $auditor = User::factory()->create([
            'role' => 'auditor',
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employeeA->id,
            'requested_by' => $employeeA->id,
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employeeB->id,
            'requested_by' => $employeeB->id,
        ]);

        $this->actingAs($hr)
            ->get(route('vacations.index'))
            ->assertOk()
            ->assertSee('Employee A')
            ->assertSee('Employee B');

        $this->actingAs($auditor)
            ->get(route('vacations.index'))
            ->assertOk()
            ->assertSee('Employee A')
            ->assertSee('Employee B');
    }

    public function test_month_filter_applies_to_request_list_with_overlap_logic(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-05-30',
            'end_date' => '2026-06-02',
            'days_requested' => 4,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($employee)
            ->get(route('vacations.index', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('10.06.2026')
            ->assertSee('30.05.2026')
            ->assertDontSee('01.05.2026');
    }

    public function test_default_month_filter_applies_on_initial_page_load(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->travelTo(Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        try {
            AbsenceRequest::factory()->create([
                'user_id' => $employee->id,
                'requested_by' => $employee->id,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
                'days_requested' => 3,
                'status' => AbsenceRequest::STATUS_PENDING,
            ]);

            AbsenceRequest::factory()->create([
                'user_id' => $employee->id,
                'requested_by' => $employee->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-03',
                'days_requested' => 3,
                'status' => AbsenceRequest::STATUS_PENDING,
            ]);

            $this->actingAs($employee)
                ->get(route('vacations.index'))
                ->assertOk()
                ->assertSee('10.06.2026')
                ->assertDontSee('01.05.2026')
                ->assertSee('value="2026-06"', false);
        } finally {
            $this->travelBack();
        }
    }
}
