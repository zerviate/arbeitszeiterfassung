<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_own_work_and_evaluations_but_not_management_or_system_navigation(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $response = $this->actingAs($employee)->get(route('time.index'));

        $response->assertOk();
        $response->assertSeeText('Arbeitszeit');
        $response->assertSeeText('Bewertungen');
        $response->assertSeeText('Heute');
        $response->assertSeeText('Korrekturen');
        $response->assertSeeText('Urlaub');
        $response->assertSeeText('Krankmeldungen');
        $response->assertSeeText('Tagesbewertung');
        $response->assertSeeText('Wochenbewertung');
        $response->assertSeeText('Monatsbewertung');
        $response->assertDontSeeText('Teamzeiten');
        $response->assertDontSee(route('contracts.index'), false);
        $response->assertDontSee(route('holidays.index'), false);
        $response->assertDontSeeText('Audit-Logs');
    }

    public function test_manager_sees_management_navigation_without_hr_admin_entries(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $response = $this->actingAs($manager)->get(route('time.index'));

        $response->assertOk();
        $response->assertSeeText('Verwaltung');
        $response->assertSeeText('Teamzeiten');
        $response->assertSeeText('Urlaubskonten');
        $response->assertDontSee(route('contracts.index'), false);
        $response->assertDontSee(route('holidays.index'), false);
        $response->assertDontSeeText('Audit-Logs');
    }

    public function test_admin_sees_management_and_system_navigation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('time.index'));

        $response->assertOk();
        $response->assertSeeText('Verwaltung');
        $response->assertSeeText('Teamzeiten');
        $response->assertSee(route('contracts.index'), false);
        $response->assertSeeText('Feiertage');
        $response->assertSeeText('Urlaubskonten');
        $response->assertSeeText('System');
        $response->assertSeeText('Audit-Logs');
    }

    public function test_hr_sees_management_and_system_navigation(): void
    {
        $hr = User::factory()->create([
            'role' => 'hr',
        ]);

        $response = $this->actingAs($hr)->get(route('time.index'));

        $response->assertOk();
        $response->assertSeeText('Verwaltung');
        $response->assertSeeText('Teamzeiten');
        $response->assertSee(route('contracts.index'), false);
        $response->assertSeeText('Feiertage');
        $response->assertSeeText('Urlaubskonten');
        $response->assertSeeText('System');
        $response->assertSeeText('Audit-Logs');
    }

    public function test_auditor_sees_system_and_read_only_management_navigation(): void
    {
        $auditor = User::factory()->create([
            'role' => 'auditor',
        ]);

        $response = $this->actingAs($auditor)->get(route('time.index'));

        $response->assertOk();
        $response->assertSeeText('Verwaltung');
        $response->assertSeeText('Teamzeiten');
        $response->assertSeeText('Urlaubskonten');
        $response->assertSeeText('System');
        $response->assertSeeText('Audit-Logs');
        $response->assertDontSee(route('contracts.index'), false);
        $response->assertDontSee(route('holidays.index'), false);
    }
}
