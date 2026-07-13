<?php

namespace Tests\Feature\VacationBalance;

use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Models\VacationBalance;
use App\Services\VacationBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_service_calculates_year_summary(): void
    {
        $user = User::factory()->create();

        $approvedRequest = AbsenceRequest::factory()->create([
            'user_id' => $user->id,
            'requested_by' => $user->id,
            'type' => AbsenceRequest::TYPE_VACATION,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-02',
            'days_requested' => 2,
            'status' => AbsenceRequest::STATUS_APPROVED,
        ]);

        AbsenceRequest::factory()->create([
            'user_id' => $user->id,
            'requested_by' => $user->id,
            'type' => AbsenceRequest::TYPE_VACATION,
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
            'days_requested' => 3,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        AbsenceRecord::factory()->create([
            'user_id' => $user->id,
            'type' => AbsenceRecord::TYPE_VACATION,
            'absence_date' => '2026-02-01',
            'absence_request_id' => $approvedRequest->id,
            'recorded_by' => $user->id,
        ]);

        AbsenceRecord::factory()->create([
            'user_id' => $user->id,
            'type' => AbsenceRecord::TYPE_VACATION,
            'absence_date' => '2026-02-02',
            'absence_request_id' => $approvedRequest->id,
            'recorded_by' => $user->id,
        ]);

        VacationBalance::factory()->create([
            'user_id' => $user->id,
            'year' => 2026,
            'annual_entitlement_days' => 30,
            'carryover_days' => 5,
            'manual_adjustment_days' => -2,
        ]);

        $summary = app(VacationBalanceService::class)->getYearSummary($user, 2026);

        $this->assertSame(33.0, $summary['available_days']);
        $this->assertSame(3.0, $summary['pending_days']);
        $this->assertSame(2.0, $summary['approved_days']);
        $this->assertSame(2.0, $summary['used_days']);
        $this->assertSame(31.0, $summary['remaining_days']);
        $this->assertSame(28.0, $summary['remaining_after_pending_days']);
    }
}
