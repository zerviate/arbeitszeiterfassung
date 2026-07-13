<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('time.contract.manage');
    }

    public function view(User $user, Contract $contract): bool
    {
        if ($user->hasPermission('time.contract.manage')) {
            return true;
        }

        if ($user->id === $contract->user_id) {
            return $user->hasPermission('time.view.own') || $user->hasPermission('time.view.all');
        }

        if ($user->hasPermission('time.view.all')) {
            return true;
        }

        $targetUser = $contract->relationLoaded('user')
            ? $contract->user
            : $contract->user()->first();

        return $targetUser !== null
            && $user->hasPermission('time.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('time.contract.manage');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->hasPermission('time.contract.manage');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasPermission('time.contract.manage');
    }
}
