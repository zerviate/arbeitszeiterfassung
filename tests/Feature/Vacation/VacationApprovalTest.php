<?php

namespace Tests\Feature\Vacation;

use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Models\VacationBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_vacation_request_and_create_records(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $this->createVacationBalance($employee, 2026, $admin->id);

        $response = $this->actingAs($admin)->post(route('vacations.approve', $vacation), [
            'review_note' => 'Genehmigt',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('absence_requests', [
            'id' => $vacation->id,
            'status' => AbsenceRequest::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseCount('absence_records', 3);

        $matchingRecordExists = AbsenceRecord::query()
            ->where('user_id', $employee->id)
            ->where('type', AbsenceRecord::TYPE_VACATION)
            ->where('absence_request_id', $vacation->id)
            ->whereDate('absence_date', '2026-06-11')
            ->exists();

        $this->assertTrue($matchingRecordExists);
    }

    public function test_hr_can_approve_any_vacation_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $this->createVacationBalance($employee, (int) $vacation->start_date->format('Y'), $hr->id);

        $response = $this->actingAs($hr)->post(route('vacations.approve', $vacation), [
            'review_note' => 'HR approved',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('absence_requests', [
            'id' => $vacation->id,
            'status' => AbsenceRequest::STATUS_APPROVED,
            'reviewed_by' => $hr->id,
        ]);
    }

    public function test_manager_can_approve_team_request_but_not_foreign_request(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $teamMember = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
        ]);

        $otherEmployee = User::factory()->create([
            'role' => 'employee',
        ]);

        $teamVacation = AbsenceRequest::factory()->create([
            'user_id' => $teamMember->id,
            'requested_by' => $teamMember->id,
            'status' => AbsenceRequest::STATUS_PENDING,
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-22',
            'days_requested' => 3,
        ]);

        $this->createVacationBalance($teamMember, 2026, $manager->id);

        $foreignVacation = AbsenceRequest::factory()->create([
            'user_id' => $otherEmployee->id,
            'requested_by' => $otherEmployee->id,
            'status' => AbsenceRequest::STATUS_PENDING,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'days_requested' => 3,
        ]);

        $this->actingAs($manager)
            ->post(route('vacations.approve', $teamVacation), ['review_note' => 'Team approved'])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('vacations.approve', $foreignVacation), ['review_note' => 'Nope'])
            ->assertForbidden();

        $this->assertDatabaseHas('absence_requests', [
            'id' => $teamVacation->id,
            'status' => AbsenceRequest::STATUS_APPROVED,
            'reviewed_by' => $manager->id,
        ]);

        $this->assertDatabaseHas('absence_requests', [
            'id' => $foreignVacation->id,
            'status' => AbsenceRequest::STATUS_PENDING,
            'reviewed_by' => null,
        ]);
    }

    public function test_hr_can_reject_pending_vacation_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $vacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($hr)->post(route('vacations.reject', $vacation), [
            'review_note' => 'Nicht moeglich in diesem Zeitraum',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('absence_requests', [
            'id' => $vacation->id,
            'status' => AbsenceRequest::STATUS_REJECTED,
            'reviewed_by' => $hr->id,
            'review_note' => 'Nicht moeglich in diesem Zeitraum',
        ]);

        $this->assertDatabaseCount('absence_records', 0);
    }

    public function test_cannot_approve_conflicting_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_APPROVED,
            'reviewed_by' => $hr->id,
            'reviewed_at' => now('UTC'),
        ]);

        $pendingVacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-08-11',
            'end_date' => '2026-08-14',
            'days_requested' => 4,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($hr)->post(route('vacations.approve', $pendingVacation), [
            'review_note' => 'Sollte scheitern',
        ]);

        $response->assertSessionHasErrors('vacation');

        $this->assertDatabaseHas('absence_requests', [
            'id' => $pendingVacation->id,
            'status' => AbsenceRequest::STATUS_PENDING,
            'reviewed_by' => null,
        ]);
    }

    public function test_cannot_approve_request_conflicting_with_sick_leave_record(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-09-11',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'reference_group' => 'sick-september',
            'recorded_by' => $hr->id,
        ]);

        $pendingVacation = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($hr)->post(route('vacations.approve', $pendingVacation), [
            'review_note' => 'Sollte scheitern',
        ]);

        $response->assertSessionHasErrors('vacation');

        $this->assertDatabaseHas('absence_requests', [
            'id' => $pendingVacation->id,
            'status' => AbsenceRequest::STATUS_PENDING,
            'reviewed_by' => null,
        ]);
    }

    private function createVacationBalance(User $employee, int $year, int $createdBy): void
    {
        VacationBalance::factory()->create([
            'user_id' => $employee->id,
            'year' => $year,
            'annual_entitlement_days' => 30,
            'carryover_days' => 0,
            'manual_adjustment_days' => 0,
            'created_by' => $createdBy,
        ]);
    }
}
