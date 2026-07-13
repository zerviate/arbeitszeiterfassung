<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\User;

class AbsenceRequestPolicy
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

    public function view(User $user, AbsenceRequest $absenceRequest): bool
    {
        if ($user->id === $absenceRequest->user_id) {
            return $user->hasAnyPermission([
                'absence.view.own',
                'absence.view.all',
            ]);
        }

        if ($user->hasPermission('absence.view.all')) {
            return true;
        }

        $targetUser = $absenceRequest->relationLoaded('user')
            ? $absenceRequest->user
            : $absenceRequest->user()->first();

        return $targetUser !== null
            && $user->hasPermission('absence.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function create(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return $user->hasAnyPermission([
                'absence.request.own',
                'absence.request.for_others',
            ]);
        }

        if (! $user->hasPermission('absence.request.for_others')) {
            return false;
        }

        if ($user->hasPermission('absence.view.all')) {
            return true;
        }

        return $user->hasPermission('absence.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function review(User $user, AbsenceRequest $absenceRequest): bool
    {
        if ($user->hasPermission('absence.review.all')) {
            return true;
        }

        if (! $user->hasPermission('absence.review.team')) {
            return false;
        }

        $targetUser = $absenceRequest->relationLoaded('user')
            ? $absenceRequest->user
            : $absenceRequest->user()->first();

        return $targetUser !== null && $user->isManagerOf($targetUser);
    }

    public function cancel(User $user, AbsenceRequest $absenceRequest): bool
    {
        if ($absenceRequest->status !== AbsenceRequest::STATUS_PENDING) {
            return false;
        }

        if ($user->id === $absenceRequest->user_id) {
            return $user->hasPermission('absence.cancel.own');
        }

        return $this->review($user, $absenceRequest);
    }
}
