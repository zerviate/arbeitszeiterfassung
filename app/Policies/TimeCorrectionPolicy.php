<?php

namespace App\Policies;

use App\Models\TimeCorrection;
use App\Models\User;

class TimeCorrectionPolicy
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

    public function view(User $user, TimeCorrection $correction): bool
    {
        if ($user->id === $correction->user_id) {
            return $user->hasPermission('time.view.own');
        }

        if ($user->hasPermission('time.view.all')) {
            return true;
        }

        $targetUser = $correction->relationLoaded('user')
            ? $correction->user
            : $correction->user()->first();

        return $targetUser !== null
            && $user->hasPermission('time.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function request(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return $user->hasPermission('time.correct.own');
        }

        return $user->hasPermission('time.correct.request.for_others');
    }

    public function review(User $user, TimeCorrection $correction): bool
    {
        return $user->hasPermission('time.correct.review');
    }
}
