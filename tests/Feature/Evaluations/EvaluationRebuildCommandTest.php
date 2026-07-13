<?php

namespace Tests\Feature\Evaluations;

use App\Models\Contract;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EvaluationRebuildCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_rebuilds_evaluation_for_selected_user_and_range(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        WorkSession::query()->create([
            'user_id' => $employee->id,
            'work_date' => '2026-05-08',
            'started_at' => '2026-05-08 08:00:00',
            'ended_at' => '2026-05-08 15:30:00',
            'gross_minutes' => 450,
            'status' => WorkSession::STATUS_CLOSED,
        ]);

        $exitCode = Artisan::call('evaluations:rebuild', [
            '--from' => '2026-05-08',
            '--to' => '2026-05-08',
            '--user_id' => $employee->id,
        ]);

        $this->assertSame(0, $exitCode);

        $evaluationExists = DailyWorktimeEvaluation::query()
            ->where('user_id', $employee->id)
            ->whereDate('work_date', '2026-05-08')
            ->where('target_minutes', 480)
            ->where('actual_minutes', 450)
            ->where('day_status', 'minor_under_target')
            ->where('traffic_light', 'yellow')
            ->exists();

        $this->assertTrue($evaluationExists);
    }
}
