<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class VisibleUserScope
{
    public static function absenceIds(User $actor): Collection
    {
        if ($actor->hasPermission('absence.view.all')) {
            return User::query()->pluck('id');
        }

        if ($actor->hasPermission('absence.view.team')) {
            return $actor->teamMembers()
                ->pluck('id')
                ->push($actor->id)
                ->unique()
                ->values();
        }

        return collect([$actor->id]);
    }

    public static function absenceUsers(User $actor): Collection
    {
        return User::query()
            ->whereIn('id', self::absenceIds($actor))
            ->orderBy('name')
            ->get();
    }
}
