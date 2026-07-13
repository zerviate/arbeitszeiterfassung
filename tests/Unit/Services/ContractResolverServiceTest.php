<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\User;
use App\Services\ContractResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_active_contract_for_date(): void
    {
        $user = User::factory()->create();

        Contract::factory()->create([
            'user_id' => $user->id,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-06-30',
            'is_active' => true,
            'weekly_minutes' => 2000,
        ]);

        $expected = Contract::factory()->create([
            'user_id' => $user->id,
            'valid_from' => '2026-07-01',
            'valid_to' => null,
            'is_active' => true,
            'weekly_minutes' => 2400,
        ]);

        $service = new ContractResolverService();

        $resolved = $service->resolveForDate($user, '2026-07-15');

        $this->assertNotNull($resolved);
        $this->assertSame($expected->id, $resolved->id);
    }
}
