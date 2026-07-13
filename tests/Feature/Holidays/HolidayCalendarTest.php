<?php

namespace Tests\Feature\Holidays;

use App\Models\Contract;
use App\Models\DailyWorktimeEvaluation;
use App\Models\HolidayCalendarEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_manage_holidays_and_evaluation_marks_holiday(): void
    {
        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->actingAs($hr)
            ->post(route('holidays.store'), [
                'holiday_date' => '2026-12-25',
                'name' => '1. Weihnachtstag',
                'is_active' => true,
            ])
            ->assertRedirect(route('holidays.index'));

        $entryExists = HolidayCalendarEntry::query()
            ->whereDate('holiday_date', '2026-12-25')
            ->where('name', '1. Weihnachtstag')
            ->where('is_active', true)
            ->where('created_by', $hr->id)
            ->exists();

        $this->assertTrue($entryExists);

        $this->actingAs($employee)
            ->get(route('evaluations.day', [$employee, '2026-12-25']))
            ->assertOk();

        $evaluationExists = DailyWorktimeEvaluation::query()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', '2026-12-25')
            ->where('is_holiday', true)
            ->where('holiday_name', '1. Weihnachtstag')
            ->where('target_minutes', 0)
            ->where('day_status', 'holiday')
            ->where('traffic_light', 'grey')
            ->exists();

        $this->assertTrue($evaluationExists);
    }

    public function test_employee_cannot_manage_holidays(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->actingAs($employee)
            ->post(route('holidays.store'), [
                'holiday_date' => '2026-12-25',
                'name' => '1. Weihnachtstag',
                'is_active' => true,
            ])
            ->assertForbidden();
    }
}
