<?php

namespace Tests\Feature\VacationBalance;

use App\Models\User;
use App\Models\VacationBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationBalanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_vacation_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($admin)->post(route('vacation-balances.store'), [
            'user_id' => $employee->id,
            'year' => 2026,
            'annual_entitlement_days' => 30,
            'carryover_days' => 2,
            'manual_adjustment_days' => 0,
            'note' => 'Initiales Konto',
        ]);

        $response->assertRedirect(route('vacation-balances.index', ['year' => 2026]));

        $balanceExists = VacationBalance::query()
            ->where('user_id', $employee->id)
            ->where('year', 2026)
            ->where('annual_entitlement_days', 30)
            ->where('carryover_days', 2)
            ->exists();

        $this->assertTrue($balanceExists);
    }

    public function test_hr_can_create_vacation_balance(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($hr)->post(route('vacation-balances.store'), [
            'user_id' => $employee->id,
            'year' => 2026,
            'annual_entitlement_days' => 28,
            'carryover_days' => 1,
            'manual_adjustment_days' => -1,
            'note' => null,
        ]);

        $response->assertRedirect(route('vacation-balances.index', ['year' => 2026]));

        $balanceExists = VacationBalance::query()
            ->where('user_id', $employee->id)
            ->where('year', 2026)
            ->where('annual_entitlement_days', 28)
            ->where('manual_adjustment_days', -1)
            ->exists();

        $this->assertTrue($balanceExists);
    }

    public function test_employee_cannot_create_vacation_balance(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $targetUser = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->post(route('vacation-balances.store'), [
                'user_id' => $targetUser->id,
                'year' => 2026,
                'annual_entitlement_days' => 30,
                'carryover_days' => 0,
                'manual_adjustment_days' => 0,
            ])
            ->assertForbidden();
    }

    public function test_employee_cannot_open_vacation_balance_management_page(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('vacation-balances.index'))
            ->assertForbidden();
    }

    public function test_auditor_can_open_vacation_balance_management_page(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($auditor)
            ->get(route('vacation-balances.index'))
            ->assertOk();
    }
}
