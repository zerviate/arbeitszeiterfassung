<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VacationBalance;

class VacationBalancePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'absence.view.team',
            'absence.view.all',
            'absence.vacation.balance.manage',
        ]);
    }

    public function view(User $user, VacationBalance $vacationBalance): bool
    {
        if ($user->hasPermission('absence.vacation.balance.manage') || $user->hasPermission('absence.view.all')) {
            return true;
        }

        $targetUser = $vacationBalance->relationLoaded('user')
            ? $vacationBalance->user
            : $vacationBalance->user()->first();

        return $targetUser !== null
            && $user->hasPermission('absence.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('absence.vacation.balance.manage');
    }

    public function update(User $user, VacationBalance $vacationBalance): bool
    {
        return $user->hasPermission('absence.vacation.balance.manage');
    }
}
