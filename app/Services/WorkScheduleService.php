<?php

namespace App\Services;

use App\Models\Contract;
use Carbon\Carbon;

class WorkScheduleService
{
    public function isScheduledWorkday(?Contract $contract, Carbon|string $date): bool
    {
        if ($contract === null) {
            return false;
        }

        $dayKey = strtolower(Carbon::parse($date)->englishDayOfWeek);
        $pattern = $this->normalizeWorkdaysPattern($contract->workdays_pattern ?? []);

        return $pattern[$dayKey] ?? false;
    }

    public function targetMinutesForDate(?Contract $contract, Carbon|string $date): int
    {
        if ($contract === null) {
            return 0;
        }

        if (! $this->isScheduledWorkday($contract, $date)) {
            return 0;
        }

        $pattern = $this->normalizeWorkdaysPattern($contract->workdays_pattern ?? []);
        $activeDays = collect($pattern)
            ->filter(static fn (bool $active): bool => $active)
            ->count();

        if ($activeDays === 0) {
            return 0;
        }

        return (int) floor(max(0, (int) $contract->weekly_minutes) / $activeDays);
    }

    public function normalizeWorkdaysPattern(array $pattern): array
    {
        $normalized = [];

        foreach (Contract::DAY_KEYS as $dayKey) {
            $value = $pattern[$dayKey] ?? false;
            $normalized[$dayKey] = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return $normalized;
    }
}
