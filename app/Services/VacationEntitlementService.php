<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacationBalance;

class VacationEntitlementService
{
    public function getOrNull(User $user, int $year): ?VacationBalance
    {
        return VacationBalance::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();
    }

    public function totalAvailableDays(VacationBalance $balance): float
    {
        return round(
            (float) $balance->annual_entitlement_days
            + (float) $balance->carryover_days
            + (float) $balance->manual_adjustment_days,
            2,
        );
    }
}
