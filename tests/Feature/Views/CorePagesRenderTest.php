<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorePagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_core_pages_render_without_errors(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->actingAs($employee)->get(route('time.index'))->assertOk();
        $this->actingAs($employee)->get(route('time.day', now('UTC')->toDateString()))->assertOk();
        $this->actingAs($employee)->get(route('time.month', now('UTC')->format('Y-m')))->assertOk();
        $this->actingAs($employee)->get(route('time.corrections.index'))->assertOk();
        $this->actingAs($employee)->get(route('vacations.index'))->assertOk();
        $this->actingAs($employee)->get(route('sick-leaves.index'))->assertOk();
        $this->actingAs($employee)->get(route('evaluations.month', [$employee, now('UTC')->format('Y-m')]))->assertOk();
    }

    public function test_admin_management_pages_render_without_errors(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)->get(route('management.time.index'))->assertOk();
        $this->actingAs($admin)->get(route('contracts.index'))->assertOk();
        $this->actingAs($admin)->get(route('holidays.index'))->assertOk();
        $this->actingAs($admin)->get(route('vacation-balances.index'))->assertOk();
        $this->actingAs($admin)->get(route('audit.index'))->assertOk();
    }

    public function test_hr_management_pages_render_without_errors(): void
    {
        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $this->actingAs($hr)->get(route('management.time.index'))->assertOk();
        $this->actingAs($hr)->get(route('contracts.index'))->assertOk();
        $this->actingAs($hr)->get(route('holidays.index'))->assertOk();
        $this->actingAs($hr)->get(route('vacation-balances.index'))->assertOk();
        $this->actingAs($hr)->get(route('audit.index'))->assertOk();
    }

    public function test_auditor_read_only_pages_render_without_errors(): void
    {
        $auditor = User::factory()->create([
            'role' => 'auditor',
        ]);

        $this->actingAs($auditor)->get(route('management.time.index'))->assertOk();
        $this->actingAs($auditor)->get(route('vacation-balances.index'))->assertOk();
        $this->actingAs($auditor)->get(route('audit.index'))->assertOk();
    }
}
