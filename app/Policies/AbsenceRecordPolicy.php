<?php

namespace App\Policies;

use App\Models\AbsenceRecord;
use App\Models\User;

class AbsenceRecordPolicy
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

    public function view(User $user, AbsenceRecord $absenceRecord): bool
    {
        if ($user->id === $absenceRecord->user_id) {
            return $user->hasAnyPermission([
                'absence.view.own',
                'absence.view.all',
            ]);
        }

        if ($user->hasPermission('absence.view.all')) {
            return true;
        }

        $targetUser = $absenceRecord->relationLoaded('user')
            ? $absenceRecord->user
            : $absenceRecord->user()->first();

        return $targetUser !== null
            && $user->hasPermission('absence.view.team')
            && $user->isManagerOf($targetUser);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AbsenceRecord $absenceRecord): bool
    {
        return false;
    }

    public function delete(User $user, AbsenceRecord $absenceRecord): bool
    {
        return false;
    }
}
