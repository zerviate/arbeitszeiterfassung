<?php

namespace App\Policies;

use App\Models\HolidayCalendarEntry;
use App\Models\User;

class HolidayCalendarEntryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('time.holiday.manage');
    }

    public function view(User $user, HolidayCalendarEntry $holidayCalendarEntry): bool
    {
        return $user->hasPermission('time.holiday.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('time.holiday.manage');
    }

    public function update(User $user, HolidayCalendarEntry $holidayCalendarEntry): bool
    {
        return $user->hasPermission('time.holiday.manage');
    }

    public function delete(User $user, HolidayCalendarEntry $holidayCalendarEntry): bool
    {
        return $user->hasPermission('time.holiday.manage');
    }
}
