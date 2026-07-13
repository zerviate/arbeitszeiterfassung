<?php

namespace Tests\Feature\Vacation;

use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_view_foreign_vacation_request(): void
    {
        $owner = User::factory()->create(['role' => 'employee']);
        $other = User::factory()->create(['role' => 'employee']);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $owner->id,
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('vacations.show', $vacation))
            ->assertForbidden();
    }

    public function test_admin_can_view_any_vacation_request(): void
    {
        $owner = User::factory()->create(['role' => 'employee']);
        $admin = User::factory()->create(['role' => 'admin']);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $owner->id,
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->get(route('vacations.show', $vacation))
            ->assertOk();
    }

    public function test_manager_can_view_team_request_but_not_foreign_non_team_request(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $teamMember = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
        ]);
        $otherEmployee = User::factory()->create(['role' => 'employee']);

        $teamVacation = AbsenceRequest::factory()->create([
            'user_id' => $teamMember->id,
            'requested_by' => $teamMember->id,
        ]);

        $otherVacation = AbsenceRequest::factory()->create([
            'user_id' => $otherEmployee->id,
            'requested_by' => $otherEmployee->id,
        ]);

        $this->actingAs($manager)
            ->get(route('vacations.show', $teamVacation))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('vacations.show', $otherVacation))
            ->assertForbidden();
    }

    public function test_auditor_has_read_access_but_cannot_review(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $auditor = User::factory()->create(['role' => 'auditor']);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($auditor)
            ->get(route('vacations.show', $vacation))
            ->assertOk();

        $this->actingAs($auditor)
            ->post(route('vacations.approve', $vacation), [
                'review_note' => 'No rights',
            ])
            ->assertForbidden();
    }

    public function test_employee_can_cancel_own_pending_request(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($employee)
            ->post(route('vacations.cancel', $vacation))
            ->assertRedirect();

        $this->assertDatabaseHas('absence_requests', [
            'id' => $vacation->id,
            'status' => AbsenceRequest::STATUS_CANCELLED,
            'cancelled_by' => $employee->id,
            'reviewed_by' => null,
        ]);
    }

    public function test_employee_cannot_cancel_approved_request(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'status' => AbsenceRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($employee)
            ->post(route('vacations.cancel', $vacation))
            ->assertForbidden();
    }
}
