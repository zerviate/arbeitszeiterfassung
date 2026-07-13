<?php

namespace Tests\Feature\Exports;

use App\Models\Contract;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_export_compliance_csv_for_day(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $teamMember = User::factory()->create([
            'role' => 'employee',
            'manager_id' => $manager->id,
            'name' => 'Team Mitglied',
        ]);

        Contract::factory()->create([
            'user_id' => $teamMember->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'is_active' => true,
        ]);

        WorkSession::query()->create([
            'user_id' => $teamMember->id,
            'work_date' => '2026-05-12',
            'started_at' => '2026-05-12 08:00:00',
            'ended_at' => '2026-05-12 16:00:00',
            'gross_minutes' => 480,
            'status' => WorkSession::STATUS_CLOSED,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('exports.compliance.day.csv', ['date' => '2026-05-12']));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Soll_Minuten', $content);
        $this->assertStringContainsString('Saldo_Minuten', $content);
        $this->assertStringContainsString('Team Mitglied', $content);
    }
}
