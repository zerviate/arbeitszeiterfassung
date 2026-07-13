<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_contract(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $response = $this->actingAs($admin)->post(route('contracts.store'), [
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'workdays_pattern' => [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => false,
                'sunday' => false,
            ],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('contracts.index'));

        $contractExists = Contract::query()
            ->where('user_id', $employee->id)
            ->where('weekly_minutes', 2400)
            ->whereDate('valid_from', '2026-01-01')
            ->where('created_by', $admin->id)
            ->where('is_active', true)
            ->exists();

        $this->assertTrue($contractExists);
    }

    public function test_hr_can_create_contract_with_manage_permission(): void
    {
        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $response = $this->actingAs($hr)->post(route('contracts.store'), [
            'user_id' => $employee->id,
            'weekly_minutes' => 1800,
            'valid_from' => '2026-04-01',
            'valid_to' => null,
            'workdays_pattern' => [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => false,
                'saturday' => false,
                'sunday' => false,
            ],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('contracts.index'));

        $this->assertDatabaseHas('contracts', [
            'user_id' => $employee->id,
            'weekly_minutes' => 1800,
            'created_by' => $hr->id,
        ]);
    }

    public function test_employee_cannot_create_contract(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $target = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->actingAs($employee)
            ->post(route('contracts.store'), [
                'user_id' => $target->id,
                'weekly_minutes' => 2400,
                'valid_from' => '2026-01-01',
                'valid_to' => null,
                'workdays_pattern' => [
                    'monday' => true,
                    'tuesday' => true,
                    'wednesday' => true,
                    'thursday' => true,
                    'friday' => true,
                    'saturday' => false,
                    'sunday' => false,
                ],
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_overlapping_active_contracts_are_blocked(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        Contract::factory()->create([
            'user_id' => $employee->id,
            'weekly_minutes' => 2400,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('contracts.store'), [
            'user_id' => $employee->id,
            'weekly_minutes' => 1200,
            'valid_from' => '2026-06-01',
            'valid_to' => null,
            'workdays_pattern' => [
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => false,
                'friday' => false,
                'saturday' => false,
                'sunday' => false,
            ],
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('contract');

        $this->assertDatabaseCount('contracts', 1);
    }
}
