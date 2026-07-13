<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSession;

class WorkSessionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'time.view.own',
            'time.view.team',
            'time.view.all',
        ]);
    }

    public function track(User $user, User $targetUser): bool
    {
        if ($user->id !== $targetUser->id) {
            return false;
        }

        return $user->hasPermission('time.track.own');
    }

    public function view(User $user, WorkSession $workSession): bool
    {
        if ($user->id === $workSession->user_id) {
            return $user->hasPermission('time.view.own') || $user->hasPermission('time.view.all');
        }

        if ($user->hasPermission('time.view.all')) {
            return true;
        }

        $sessionUser = $workSession->relationLoaded('user')
            ? $workSession->user
            : $workSession->user()->first();

        return $sessionUser !== null
            && $user->hasPermission('time.view.team')
            && $user->isManagerOf($sessionUser);
    }
}
