<?php

namespace Tests\Feature\SickLeave;

use App\Models\AbsenceRecord;
use App\Models\SickLeaveGroup;
use App\Models\User;
use App\Services\SickLeaveConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SickLeaveUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_sick_leave_series(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $group = SickLeaveGroup::factory()->create([
            'group_key' => 'sick-test-group',
            'user_id' => $employee->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-02',
            'note' => 'alt',
            'recorded_by' => $admin->id,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-05-01',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'note' => 'alt',
            'reference_group' => $group->group_key,
            'sick_leave_group_id' => $group->id,
            'recorded_by' => $admin->id,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-05-02',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'note' => 'alt',
            'reference_group' => $group->group_key,
            'sick_leave_group_id' => $group->id,
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('sick-leaves.update', $group), [
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-04',
            'note' => 'Verlaengert',
        ]);

        $response->assertRedirect(route('sick-leaves.show', $group));

        $matchingRecordExists = AbsenceRecord::query()
            ->where('sick_leave_group_id', $group->id)
            ->where('type', AbsenceRecord::TYPE_SICK_LEAVE)
            ->whereDate('absence_date', '2026-05-04')
            ->where('note', 'Verlaengert')
            ->exists();

        $this->assertTrue($matchingRecordExists);

        $this->assertDatabaseCount('absence_records', 4);

        $this->assertDatabaseHas('sick_leave_groups', [
            'id' => $group->id,
            'note' => 'Verlaengert',
            'recorded_by' => $admin->id,
        ]);

        $this->assertSame('2026-05-01', $group->refresh()->start_date?->toDateString());
        $this->assertSame('2026-05-04', $group->end_date?->toDateString());
    }

    public function test_admin_cannot_update_sick_leave_when_other_absence_conflicts(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $group = SickLeaveGroup::factory()->create([
            'group_key' => 'sick-test-group',
            'user_id' => $employee->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-02',
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

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-05-02',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'reference_group' => $group->group_key,
            'sick_leave_group_id' => $group->id,
            'recorded_by' => $admin->id,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_VACATION,
            'absence_date' => '2026-05-04',
            'source' => AbsenceRecord::SOURCE_REQUEST_APPROVED,
            'recorded_by' => $admin->id,
        ]);

        $hasConflict = app(SickLeaveConflictService::class)->hasAnyAbsenceConflict(
            $employee,
            '2026-05-01',
            '2026-05-04',
            $group->id,
        );

        $this->assertTrue($hasConflict);

        $response = $this->actingAs($admin)->put(route('sick-leaves.update', $group), [
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-04',
            'note' => 'Verlaengert',
        ]);

        $response->assertSessionHasErrors('sick_leave');

        $this->assertDatabaseCount('absence_records', 3);

        $this->assertDatabaseMissing('absence_records', [
            'sick_leave_group_id' => $group->id,
            'absence_date' => '2026-05-04',
        ]);
    }
}
