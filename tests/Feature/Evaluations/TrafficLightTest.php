<?php

namespace Tests\Feature\Evaluations;

use App\Services\TrafficLightService;
use Tests\TestCase;

class TrafficLightTest extends TestCase
{
    public function test_green_when_target_is_fulfilled(): void
    {
        $service = new TrafficLightService();

        $result = $service->evaluateDay(
            targetMinutes: 480,
            actualMinutes: 480,
            vacationMinutes: 0,
            sickLeaveMinutes: 0,
            isScheduledWorkday: true,
            flags: [],
        );

        $this->assertSame('green', $result['traffic_light']);
        $this->assertSame('fulfilled', $result['day_status']);
        $this->assertSame(0, $result['balance_minutes']);
    }

    public function test_yellow_for_minor_under_target(): void
    {
        $service = new TrafficLightService();

        $result = $service->evaluateDay(
            targetMinutes: 480,
            actualMinutes: 460,
            vacationMinutes: 0,
            sickLeaveMinutes: 0,
            isScheduledWorkday: true,
            flags: [],
        );

        $this->assertSame('yellow', $result['traffic_light']);
        $this->assertSame('minor_under_target', $result['day_status']);
        $this->assertSame(-20, $result['balance_minutes']);
    }

    public function test_sick_leave_is_green_and_balanced(): void
    {
        $service = new TrafficLightService();

        $result = $service->evaluateDay(
            targetMinutes: 480,
            actualMinutes: 0,
            vacationMinutes: 0,
            sickLeaveMinutes: 480,
            isScheduledWorkday: true,
            flags: [],
        );

        $this->assertSame('green', $result['traffic_light']);
        $this->assertSame('sick_leave', $result['day_status']);
        $this->assertSame(0, $result['balance_minutes']);
    }

    public function test_holiday_without_work_is_grey(): void
    {
        $service = new TrafficLightService();

        $result = $service->evaluateDay(
            targetMinutes: 0,
            actualMinutes: 0,
            vacationMinutes: 0,
            sickLeaveMinutes: 0,
            isScheduledWorkday: false,
            isHoliday: true,
            flags: [],
        );

        $this->assertSame('grey', $result['traffic_light']);
        $this->assertSame('holiday', $result['day_status']);
        $this->assertSame(0, $result['balance_minutes']);
    }
}
