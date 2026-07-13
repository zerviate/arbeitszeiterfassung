<?php

namespace Tests\Feature\Vacation;

use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_own_vacation_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)->post(route('vacations.store'), [
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-22',
            'reason' => 'Kurzurlaub',
        ]);

        $response->assertRedirect(route('vacations.index'));

        $this->assertDatabaseHas('absence_requests', [
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'type' => AbsenceRequest::TYPE_VACATION,
            'status' => AbsenceRequest::STATUS_PENDING,
            'days_requested' => 3,
        ]);
    }

    public function test_overlapping_request_is_blocked(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-22',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($employee)->post(route('vacations.store'), [
            'start_date' => '2026-04-22',
            'end_date' => '2026-04-24',
            'reason' => 'Ueberlappung',
        ]);

        $response->assertSessionHasErrors('vacation');

        $this->assertDatabaseCount('absence_requests', 1);
    }

    public function test_request_is_blocked_when_sick_leave_record_exists(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-04-22',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'reference_group' => 'sick-existing-group',
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($employee)->post(route('vacations.store'), [
            'start_date' => '2026-04-22',
            'end_date' => '2026-04-24',
            'reason' => 'Konflikt mit Krankheit',
        ]);

        $response->assertSessionHasErrors('vacation');

        $this->assertDatabaseCount('absence_requests', 0);
    }

    public function test_manager_can_create_vacation_for_team_member(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->post(route('vacations.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-07',
            'reason' => 'Teamurlaub',
        ]);

        $response->assertRedirect(route('vacations.index'));

        $this->assertDatabaseHas('absence_requests', [
            'user_id' => $employee->id,
            'requested_by' => $manager->id,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);
    }
}
