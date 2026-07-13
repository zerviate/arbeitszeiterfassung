<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Models\DailyTimeSummary;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyWorktimeEvaluationService
{
    public function __construct(
        private readonly ContractResolverService $contractResolverService,
        private readonly WorkScheduleService $workScheduleService,
        private readonly HolidayCalendarService $holidayCalendarService,
        private readonly TrafficLightService $trafficLightService,
    ) {
    }

    public function rebuildForUserAndDate(User $user, Carbon|string $date): DailyWorktimeEvaluation
    {
        $workDate = Carbon::parse($date)->toDateString();

        $contract = $this->contractResolverService->resolveForDate($user, $workDate);
        $targetMinutes = $this->workScheduleService->targetMinutesForDate($contract, $workDate);
        $isScheduledWorkday = $this->workScheduleService->isScheduledWorkday($contract, $workDate);
        $holidayEntry = $this->holidayCalendarService->resolveForDate($workDate);
        $isHoliday = $holidayEntry !== null;

        if ($isHoliday) {
            $targetMinutes = 0;
            $isScheduledWorkday = false;
        }

        $summary = DailyTimeSummary::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        $actualMinutes = max(0, (int) ($summary?->net_minutes ?? 0));

        $absenceRecords = AbsenceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('absence_date', $workDate)
            ->get();

        $hasVacation = $absenceRecords->contains(
            static fn (AbsenceRecord $record): bool => $record->type === AbsenceRecord::TYPE_VACATION
        );
        $hasSickLeave = $absenceRecords->contains(
            static fn (AbsenceRecord $record): bool => $record->type === AbsenceRecord::TYPE_SICK_LEAVE
        );

        $vacationMinutes = $isHoliday ? 0 : ($hasVacation ? $targetMinutes : 0);
        $sickLeaveMinutes = $isHoliday ? 0 : ($hasSickLeave ? $targetMinutes : 0);

        $flags = is_array($summary?->violation_flags) ? $summary->violation_flags : [];

        if (($summary?->has_open_entries ?? false) && ! in_array('open_session', $flags, true)) {
            $flags[] = 'open_session';
        }

        if ($isHoliday && ! in_array('holiday', $flags, true)) {
            $flags[] = 'holiday';
        }

        if ($isHoliday && ($hasVacation || $hasSickLeave) && ! in_array('absence_on_holiday', $flags, true)) {
            $flags[] = 'absence_on_holiday';
        }

        if (! $isHoliday && $contract === null && ! in_array('missing_contract', $flags, true)) {
            $flags[] = 'missing_contract';
        }

        $flags = array_values(array_unique($flags));

        $evaluated = $this->trafficLightService->evaluateDay(
            targetMinutes: $targetMinutes,
            actualMinutes: $actualMinutes,
            vacationMinutes: $vacationMinutes,
            sickLeaveMinutes: $sickLeaveMinutes,
            isScheduledWorkday: $isScheduledWorkday,
            isHoliday: $isHoliday,
            flags: $flags,
        );

        $payload = [
            'contract_id' => $contract?->id,
            'is_scheduled_workday' => $isScheduledWorkday,
            'is_holiday' => $isHoliday,
            'holiday_name' => $holidayEntry?->name,
            'target_minutes' => $targetMinutes,
            'actual_minutes' => $actualMinutes,
            'vacation_minutes' => $vacationMinutes,
            'sick_leave_minutes' => $sickLeaveMinutes,
            'balance_minutes' => $evaluated['balance_minutes'],
            'day_status' => $evaluated['day_status'],
            'traffic_light' => $evaluated['traffic_light'],
            'flags' => $flags,
        ];

        $evaluation = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if ($evaluation !== null) {
            $evaluation->forceFill($payload)->save();

            return $evaluation->fresh();
        }

        return DailyWorktimeEvaluation::query()->create(array_merge($payload, [
            'user_id' => $user->id,
            'work_date' => $workDate,
        ]));
    }

    public function rebuildForUserAndRange(User $user, Carbon|string $startDate, Carbon|string $endDate): Collection
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $evaluations = collect();

        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $evaluations->push($this->rebuildForUserAndDate($user, $cursor->toDateString()));
            $cursor->addDay();
        }

        return $evaluations;
    }
}
