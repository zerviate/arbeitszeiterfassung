<?php

namespace Tests\Feature\Security;

use App\Models\DailyTimeSummary;
use App\Models\TimeCorrection;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\SessionRebuildService;
use App\Services\TimeExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_throttles_repeated_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
        ]);

        $statuses = [];

        for ($attempt = 1; $attempt <= 15; $attempt++) {
            $response = $this->from(route('login'))->post(route('login.store'), [
                'email' => 'victim@example.com',
                'password' => 'wrong-password',
            ]);

            $statuses[] = $response->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'Expected throttling after repeated failed login attempts.');
    }

    public function test_finalized_day_cannot_be_modified_via_clock_in(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
            'timezone' => 'UTC',
        ]);

        $workDate = '2026-06-10';

        DB::table('daily_time_summaries')->insert([
            'user_id' => $employee->id,
            'work_date' => $workDate,
            'gross_minutes' => 480,
            'break_minutes' => 30,
            'net_minutes' => 450,
            'overtime_minutes' => 0,
            'has_open_entries' => false,
            'has_manual_corrections' => false,
            'violation_flags' => json_encode([]),
            'finalized_at' => now('UTC'),
            'finalized_by' => $manager->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->actingAs($employee)
            ->postJson('/api/time/clock-in', [
                'occurred_at' => $workDate.'T08:00:00Z',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('work_date');

        $this->assertFalse(
            WorkSession::query()
                ->where('user_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->where('status', WorkSession::STATUS_OPEN)
                ->exists()
        );
    }

    public function test_finalized_day_cannot_be_changed_by_correction_approval(): void
    {
        $reviewer = User::factory()->create([
            'role' => 'hr',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
            'timezone' => 'UTC',
        ]);

        $workDate = '2026-06-11';

        DB::table('daily_time_summaries')->insert([
            'user_id' => $employee->id,
            'work_date' => $workDate,
            'gross_minutes' => 0,
            'break_minutes' => 0,
            'net_minutes' => 0,
            'overtime_minutes' => 0,
            'has_open_entries' => false,
            'has_manual_corrections' => false,
            'violation_flags' => json_encode([]),
            'finalized_at' => now('UTC'),
            'finalized_by' => $reviewer->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $correction = TimeCorrection::query()->create([
            'user_id' => $employee->id,
            'work_date' => $workDate,
            'old_values' => null,
            'new_values' => [
                'events' => [
                    [
                        'type' => TimeEvent::TYPE_CLOCK_IN,
                        'occurred_at' => $workDate.'T08:00:00Z',
                    ],
                    [
                        'type' => TimeEvent::TYPE_CLOCK_OUT,
                        'occurred_at' => $workDate.'T16:00:00Z',
                    ],
                ],
            ],
            'reason' => 'Security probe correction',
            'status' => TimeCorrection::STATUS_PENDING,
            'requested_by' => $employee->id,
        ]);

        $this->actingAs($reviewer)
            ->postJson('/api/time/corrections/'.$correction->id.'/approve', [
                'review_note' => 'approved by probe',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('work_date');

        $this->assertDatabaseHas('time_corrections', [
            'id' => $correction->id,
            'status' => TimeCorrection::STATUS_PENDING,
        ]);

        $this->assertFalse(
            TimeEvent::query()
                ->where('user_id', $employee->id)
                ->where('type', TimeEvent::TYPE_MANUAL_CORRECTION)
                ->whereDate('work_date', $workDate)
                ->exists()
        );
    }

    public function test_finalized_day_cannot_receive_new_correction_request(): void
    {
        $reviewer = User::factory()->create([
            'role' => 'hr',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
            'timezone' => 'UTC',
        ]);

        $workDate = '2026-06-11';

        DB::table('daily_time_summaries')->insert([
            'user_id' => $employee->id,
            'work_date' => $workDate,
            'gross_minutes' => 0,
            'break_minutes' => 0,
            'net_minutes' => 0,
            'overtime_minutes' => 0,
            'has_open_entries' => false,
            'has_manual_corrections' => false,
            'violation_flags' => json_encode([]),
            'finalized_at' => now('UTC'),
            'finalized_by' => $reviewer->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->actingAs($employee)
            ->postJson('/api/time/corrections', [
                'work_date' => $workDate,
                'reason' => 'Security probe correction request',
                'new_values' => [
                    'events' => [
                        [
                            'type' => TimeEvent::TYPE_CLOCK_IN,
                            'occurred_at' => $workDate.'T08:00:00Z',
                        ],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('work_date');

        $this->assertDatabaseMissing('time_corrections', [
            'user_id' => $employee->id,
            'work_date' => $workDate,
            'reason' => 'Security probe correction request',
        ]);
    }

    public function test_session_rebuild_rejects_zero_minute_sessions(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'timezone' => 'UTC',
        ]);

        $workDate = '2026-06-12';
        $sameTime = '2026-06-12 08:00:00';

        DB::table('time_events')->insert([
            'user_id' => $user->id,
            'type' => TimeEvent::TYPE_CLOCK_IN,
            'occurred_at' => $sameTime,
            'work_date' => $workDate,
            'source' => TimeEvent::SOURCE_WEB,
            'created_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('time_events')->insert([
            'user_id' => $user->id,
            'type' => TimeEvent::TYPE_CLOCK_OUT,
            'occurred_at' => $sameTime,
            'work_date' => $workDate,
            'source' => TimeEvent::SOURCE_WEB,
            'created_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        try {
            app(SessionRebuildService::class)->rebuildForUserAndDate($user->id, $workDate);
            $this->fail('Expected validation error for zero-minute session rebuild.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('events', $exception->errors());
        }

        $this->assertFalse(
            WorkSession::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $workDate)
                ->where('gross_minutes', 0)
                ->where('status', WorkSession::STATUS_CLOSED)
                ->exists()
        );
    }

    public function test_export_rows_are_csv_formula_sanitized(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
            'name' => '=HYPERLINK("https://example.test","click")',
            'timezone' => 'UTC',
        ]);

        $workDate = '2026-06-13';

        DB::table('daily_time_summaries')->insert([
            'user_id' => $employee->id,
            'work_date' => $workDate,
            'gross_minutes' => 480,
            'break_minutes' => 30,
            'net_minutes' => 450,
            'overtime_minutes' => 0,
            'has_open_entries' => false,
            'has_manual_corrections' => false,
            'violation_flags' => json_encode([]),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $rows = app(TimeExportService::class)->getDayExportRows($admin, $workDate);

        $employeeRow = collect($rows)->first(
            fn (array $row): bool => ($row['Mitarbeiter'] ?? null) === "'".$employee->name
        );

        $this->assertNotNull($employeeRow);
        $this->assertFalse(str_starts_with((string) $employeeRow['Mitarbeiter'], '='));
        $this->assertTrue(str_starts_with((string) $employeeRow['Mitarbeiter'], "'="));
    }

    public function test_security_headers_are_set_on_login_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $contentSecurityPolicy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $contentSecurityPolicy);
        $this->assertStringContainsString("frame-ancestors 'none'", $contentSecurityPolicy);
    }
}
