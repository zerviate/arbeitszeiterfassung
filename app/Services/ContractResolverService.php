<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\User;
use Carbon\Carbon;

class ContractResolverService
{
    public function resolveForDate(User $user, Carbon|string $date): ?Contract
    {
        $date = Carbon::parse($date)->toDateString();

        return Contract::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }
}
