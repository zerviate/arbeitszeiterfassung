<?php

namespace Tests\Feature\VacationBalance;

use App\Models\AbsenceRequest;
use App\Models\User;
use App\Models\VacationBalance;
use App\Services\VacationApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacationApprovalAgainstBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vacation_cannot_be_approved_when_balance_is_insufficient(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $admin = User::factory()->create(['role' => 'admin']);

        VacationBalance::factory()->create([
            'user_id' => $employee->id,
            'year' => 2026,
            'annual_entitlement_days' => 2,
            'carryover_days' => 0,
            'manual_adjustment_days' => 0,
            'created_by' => $admin->id,
        ]);

        $request = AbsenceRequest::factory()->create([
            'user_id' => $employee->id,
            'requested_by' => $employee->id,
            'type' => AbsenceRequest::TYPE_VACATION,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'days_requested' => 5,
            'status' => AbsenceRequest::STATUS_PENDING,
        ]);

        $this->expectException(ValidationException::class);

        app(VacationApprovalService::class)->approve($request, $admin, 'Nicht genug Urlaub');
    }
}
