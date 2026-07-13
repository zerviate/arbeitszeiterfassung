<?php

namespace Tests\Feature\SickLeave;

use App\Models\AbsenceRecord;
use App\Models\SickLeaveGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SickLeaveAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_create_sick_leave(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $target = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->actingAs($employee)
            ->post(route('sick-leaves.store'), [
                'user_id' => $target->id,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
            ])
            ->assertForbidden();
    }

    public function test_employee_can_view_own_sick_leave(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $group = SickLeaveGroup::factory()->create([
            'group_key' => 'sick-own-group',
            'user_id' => $employee->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-01',
            'recorded_by' => $admin->id,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-05-01',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'reference_group' => $group->group_key,
            'sick_leave_group_id' => $group->id,
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($employee)
            ->get(route('sick-leaves.show', $group))
            ->assertOk();
    }

    public function test_employee_cannot_view_foreign_sick_leave(): void
    {
        $owner = User::factory()->create([
            'role' => 'employee',
        ]);

        $other = User::factory()->create([
            'role' => 'employee',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $group = SickLeaveGroup::factory()->create([
            'group_key' => 'sick-foreign-group',
            'user_id' => $owner->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-01',
            'recorded_by' => $admin->id,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $owner->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-05-01',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'reference_group' => $group->group_key,
            'sick_leave_group_id' => $group->id,
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($other)
            ->get(route('sick-leaves.show', $group))
            ->assertForbidden();
    }

    public function test_manager_can_view_team_sick_leave_but_cannot_edit(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $teamMember = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $group = SickLeaveGroup::factory()->create([
            'group_key' => 'sick-team-group',
            'user_id' => $teamMember->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-01',
            'recorded_by' => $admin->id,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $teamMember->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-05-01',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'reference_group' => $group->group_key,
            'sick_leave_group_id' => $group->id,
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($manager)
            ->get(route('sick-leaves.show', $group))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('sick-leaves.edit', $group))
            ->assertForbidden();
    }

    public function test_hr_can_create_and_edit_sick_leave(): void
    {
        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $createResponse = $this->actingAs($hr)->post(route('sick-leaves.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
            'note' => 'Erkaltung',
        ]);

        $createResponse->assertRedirect();

        $group = SickLeaveGroup::query()->where('user_id', $employee->id)->firstOrFail();

        $this->actingAs($hr)
            ->get(route('sick-leaves.edit', $group))
            ->assertOk();
    }

    public function test_only_admin_can_export_sick_leave_month_report(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $this->actingAs($admin)
            ->get(route('exports.sick-leaves.month.csv', ['month' => '2026-05']))
            ->assertOk();

        $this->actingAs($hr)
            ->get(route('exports.sick-leaves.month.csv', ['month' => '2026-05']))
            ->assertForbidden();
    }
}
