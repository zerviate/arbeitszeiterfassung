<?php

namespace App\Policies;

use App\Models\DailyWorktimeEvaluation;
use App\Models\User;

class DailyWorktimeEvaluationPolicy
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

    public function viewManagement(User $user): bool
    {
        return $user->hasAnyPermission([
            'time.view.team',
            'time.view.all',
        ]);
    }

    public function view(User $user, DailyWorktimeEvaluation $evaluation): bool
    {
        if ($user->id === $evaluation->user_id) {
            return $user->hasPermission('time.view.own');
        }

        if ($user->hasPermission('time.view.all')) {
            return true;
        }

        $targetUser = $evaluation->relationLoaded('user')
            ? $evaluation->user
            : $evaluation->user()->first();

        return $targetUser !== null
            && $user->hasPermission('time.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function viewDay(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return $user->hasPermission('time.view.own') || $user->hasPermission('time.view.all');
        }

        if ($user->hasPermission('time.view.all')) {
            return true;
        }

        return $user->hasPermission('time.view.team') && $user->isManagerOf($targetUser);
    }

    public function viewWeek(User $user, User $targetUser): bool
    {
        return $this->viewDay($user, $targetUser);
    }

    public function viewMonth(User $user, User $targetUser): bool
    {
        return $this->viewDay($user, $targetUser);
    }
}
