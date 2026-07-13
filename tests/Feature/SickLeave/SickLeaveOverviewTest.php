<?php

namespace Tests\Feature\SickLeave;

use App\Models\SickLeaveGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SickLeaveOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_month_filter_applies_on_initial_page_load(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->travelTo(Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        try {
            SickLeaveGroup::factory()->create([
                'group_key' => 'sick-june-group',
                'user_id' => $employee->id,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
                'recorded_by' => $admin->id,
            ]);

            SickLeaveGroup::factory()->create([
                'group_key' => 'sick-may-group',
                'user_id' => $employee->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-03',
                'recorded_by' => $admin->id,
            ]);

            $this->actingAs($employee)
                ->get(route('sick-leaves.index'))
                ->assertOk()
                ->assertSee('10.06.2026')
                ->assertDontSee('01.05.2026')
                ->assertSee('value="2026-06"', false);
        } finally {
            $this->travelBack();
        }
    }
}
