<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Services\WorkScheduleService;
use Tests\TestCase;

class WorkScheduleServiceTest extends TestCase
{
    public function test_target_minutes_are_distributed_over_active_days(): void
    {
        $contract = new Contract([
            'weekly_minutes' => 2400,
            'workdays_pattern' => [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => false,
                'sunday' => false,
            ],
        ]);

        $service = new WorkScheduleService();

        $this->assertTrue($service->isScheduledWorkday($contract, '2026-05-04'));
        $this->assertFalse($service->isScheduledWorkday($contract, '2026-05-09'));
        $this->assertSame(480, $service->targetMinutesForDate($contract, '2026-05-04'));
        $this->assertSame(0, $service->targetMinutesForDate($contract, '2026-05-09'));
    }
}
