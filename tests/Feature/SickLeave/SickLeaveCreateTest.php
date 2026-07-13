<?php

namespace Tests\Feature\SickLeave;

use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\SickLeaveGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SickLeaveCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_sick_leave_series(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $response = $this->actingAs($admin)->post(route('sick-leaves.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'note' => 'Infekt',
        ]);

        $response->assertRedirect();

        $records = AbsenceRecord::query()
            ->where('user_id', $employee->id)
            ->where('type', AbsenceRecord::TYPE_SICK_LEAVE)
            ->orderBy('absence_date')
            ->get();

        $this->assertCount(3, $records);
        $this->assertNotNull($records->first()?->reference_group);
        $this->assertSame($records->first()?->reference_group, $records->last()?->reference_group);

        $sickLeaveGroup = SickLeaveGroup::query()
            ->where('user_id', $employee->id)
            ->first();

        $this->assertNotNull($sickLeaveGroup);
        $this->assertSame($sickLeaveGroup?->id, $records->first()?->sick_leave_group_id);
        $this->assertSame($sickLeaveGroup?->group_key, $records->first()?->reference_group);

        $matchingRecordExists = AbsenceRecord::query()
            ->where('user_id', $employee->id)
            ->where('type', AbsenceRecord::TYPE_SICK_LEAVE)
            ->where('source', AbsenceRecord::SOURCE_ADMIN_RECORDED)
            ->where('recorded_by', $admin->id)
            ->where('note', 'Infekt')
            ->whereDate('absence_date', '2026-06-11')
            ->exists();

        $this->assertTrue($matchingRecordExists);
    }

    public function test_cannot_create_sick_leave_when_absence_conflict_exists(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_VACATION,
            'absence_date' => '2026-06-11',
            'source' => AbsenceRecord::SOURCE_REQUEST_APPROVED,
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('sick-leaves.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'note' => 'Krank',
        ]);

        $response->assertSessionHasErrors('sick_leave');

        $this->assertDatabaseCount('absence_records', 1);
    }

    public function test_cannot_create_sick_leave_when_pending_vacation_request_exists(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'type' => AbsenceRequest::TYPE_VACATION,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post(route('sick-leaves.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'note' => 'Krank',
        ]);

        $response->assertSessionHasErrors('sick_leave');

        $this->assertDatabaseCount('absence_records', 0);
    }
}
