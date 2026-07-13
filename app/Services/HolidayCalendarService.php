<?php

namespace App\Services;

use App\Models\HolidayCalendarEntry;
use Carbon\Carbon;

class HolidayCalendarService
{
    public function resolveForDate(Carbon|string $date): ?HolidayCalendarEntry
    {
        $date = Carbon::parse($date)->toDateString();

        return HolidayCalendarEntry::query()
            ->whereDate('holiday_date', $date)
            ->where('is_active', true)
            ->first();
    }

    public function isHoliday(Carbon|string $date): bool
    {
        return $this->resolveForDate($date) !== null;
    }
}
