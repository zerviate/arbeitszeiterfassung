<?php

namespace App\Policies;

use App\Models\DailyTimeSummary;
use App\Models\User;

class DailyTimeSummaryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, DailyTimeSummary $summary): bool
    {
        if ($user->id === $summary->user_id) {
            return $user->hasPermission('time.view.own');
        }

        if ($user->hasPermission('time.view.all')) {
            return true;
        }

        $targetUser = $summary->relationLoaded('user')
            ? $summary->user
            : $summary->user()->first();

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

    public function viewMonth(User $user, User $targetUser): bool
    {
        return $this->viewDay($user, $targetUser);
    }

    public function finalize(User $user, User $targetUser): bool
    {
        if ($user->hasPermission('time.finalize.all')) {
            return true;
        }

        return $user->hasPermission('time.finalize.team') && $user->isManagerOf($targetUser);
    }

    public function unfinalize(User $user, User $targetUser): bool
    {
        return $this->finalize($user, $targetUser);
    }
}
