<?php

namespace Tests\Feature\SickLeave;

use App\Models\AbsenceRecord;
use App\Models\SickLeaveGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SickLeaveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_crud_sick_leave_via_api(): void
    {
        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $createResponse = $this->actingAs($hr)->postJson('/api/sick-leaves', [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'note' => 'Grippe',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id);

        $groupKey = $createResponse->json('data.group_key');

        $this->assertIsString($groupKey);

        $updateResponse = $this->actingAs($hr)->putJson('/api/sick-leaves/'.$groupKey, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'note' => 'Verlaengert',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.note', 'Verlaengert');

        $this->assertDatabaseHas('sick_leave_groups', [
            'group_key' => $groupKey,
            'note' => 'Verlaengert',
        ]);

        $this->assertSame(
            '2026-08-03',
            SickLeaveGroup::query()->where('group_key', $groupKey)->firstOrFail()->end_date?->toDateString()
        );

        $this->actingAs($hr)
            ->deleteJson('/api/sick-leaves/'.$groupKey)
            ->assertStatus(204);

        $this->assertDatabaseMissing('sick_leave_groups', [
            'group_key' => $groupKey,
        ]);
    }

    public function test_employee_cannot_manage_sick_leave_via_api(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $target = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->actingAs($employee)
            ->postJson('/api/sick-leaves', [
                'user_id' => $target->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-02',
            ])
            ->assertForbidden();
    }

    public function test_employee_sees_only_own_sick_leaves_via_api(): void
    {
        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $other = User::factory()->create([
            'role' => 'employee',
        ]);

        $ownGroup = SickLeaveGroup::factory()->create([
            'group_key' => 'sick-own-api',
            'user_id' => $employee->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'recorded_by' => $hr->id,
        ]);

        SickLeaveGroup::factory()->create([
            'group_key' => 'sick-foreign-api',
            'user_id' => $other->id,
            'start_date' => '2026-08-11',
            'end_date' => '2026-08-11',
            'recorded_by' => $hr->id,
        ]);

        AbsenceRecord::query()->create([
            'user_id' => $employee->id,
            'type' => AbsenceRecord::TYPE_SICK_LEAVE,
            'absence_date' => '2026-08-10',
            'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
            'reference_group' => $ownGroup->group_key,
            'sick_leave_group_id' => $ownGroup->id,
            'recorded_by' => $hr->id,
        ]);

        $response = $this->actingAs($employee)
            ->getJson('/api/sick-leaves');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group_key', 'sick-own-api');
    }
}
