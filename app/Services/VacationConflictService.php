<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Models\AbsenceRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class VacationConflictService
{
    public function hasConflictingVacationRequest(User $user, Carbon|string $startDate, Carbon|string $endDate, ?int $ignoreRequestId = null): bool
    {
        [$startDate, $endDate] = $this->resolveRange($startDate, $endDate);

        return AbsenceRequest::query()
            ->where('user_id', $user->id)
            ->where('type', AbsenceRequest::TYPE_VACATION)
            ->whereIn('status', [
                AbsenceRequest::STATUS_PENDING,
                AbsenceRequest::STATUS_APPROVED,
            ])
            ->when($ignoreRequestId !== null, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }

    public function hasConflictingVacationRecord(User $user, Carbon|string $startDate, Carbon|string $endDate): bool
    {
        [$startDate, $endDate] = $this->resolveRange($startDate, $endDate);

        return AbsenceRecord::query()
            ->where('user_id', $user->id)
            ->where('type', AbsenceRecord::TYPE_VACATION)
            ->whereDate('absence_date', '>=', $startDate)
            ->whereDate('absence_date', '<=', $endDate)
            ->exists();
    }

    public function hasConflictingAbsenceRecord(User $user, Carbon|string $startDate, Carbon|string $endDate): bool
    {
        [$startDate, $endDate] = $this->resolveRange($startDate, $endDate);

        return AbsenceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('absence_date', '>=', $startDate)
            ->whereDate('absence_date', '<=', $endDate)
            ->exists();
    }

    public function countDays(Carbon|string $startDate, Carbon|string $endDate): int
    {
        $dates = $this->expandDates($startDate, $endDate);

        return count($dates);
    }

    public function expandDates(Carbon|string $startDate, Carbon|string $endDate): array
    {
        [$startDate, $endDate] = $this->resolveRange($startDate, $endDate);

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(static fn (Carbon $date): string => $date->toDateString())
            ->values()
            ->all();
    }

    private function resolveRange(Carbon|string $startDate, Carbon|string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lessThan($start)) {
            return [$end->toDateString(), $start->toDateString()];
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}
