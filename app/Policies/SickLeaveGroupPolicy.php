<?php

namespace App\Policies;

use App\Models\SickLeaveGroup;
use App\Models\User;

class SickLeaveGroupPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'absence.view.own',
            'absence.view.team',
            'absence.view.all',
        ]);
    }

    public function view(User $user, SickLeaveGroup $sickLeaveGroup): bool
    {
        if ($user->id === $sickLeaveGroup->user_id) {
            return $user->hasAnyPermission([
                'absence.view.own',
                'absence.view.all',
            ]);
        }

        if ($user->hasPermission('absence.view.all')) {
            return true;
        }

        $targetUser = $sickLeaveGroup->relationLoaded('user')
            ? $sickLeaveGroup->user
            : $sickLeaveGroup->user()->first();

        return $targetUser !== null
            && $user->hasPermission('absence.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('absence.sick.manage');
    }

    public function update(User $user, SickLeaveGroup $sickLeaveGroup): bool
    {
        return $user->hasPermission('absence.sick.manage');
    }

    public function delete(User $user, SickLeaveGroup $sickLeaveGroup): bool
    {
        return $user->hasPermission('absence.sick.manage');
    }
}
