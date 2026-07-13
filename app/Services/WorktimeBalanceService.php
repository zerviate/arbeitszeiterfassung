<?php

namespace App\Services;

use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorktimeBalanceService
{
    public function getWeekSummary(User $user, Carbon|string $date): array
    {
        $date = Carbon::parse($date);

        $startDate = $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endDate = $date->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $evaluations = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get();

        return $this->summarize($evaluations, $startDate, $endDate, 'week');
    }

    public function getMonthSummary(User $user, Carbon|string $date): array
    {
        $date = Carbon::parse($date);

        $startDate = $date->copy()->startOfMonth()->toDateString();
        $endDate = $date->copy()->endOfMonth()->toDateString();

        $evaluations = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get();

        return $this->summarize($evaluations, $startDate, $endDate, 'month');
    }

    private function summarize(Collection $evaluations, string $startDate, string $endDate, string $scope): array
    {
        $targetMinutes = (int) $evaluations->sum('target_minutes');
        $actualMinutes = (int) $evaluations->sum('actual_minutes');
        $vacationMinutes = (int) $evaluations->sum('vacation_minutes');
        $sickLeaveMinutes = (int) $evaluations->sum('sick_leave_minutes');
        $balanceMinutes = (int) $evaluations->sum('balance_minutes');

        $trafficLight = 'grey';

        if ($evaluations->isNotEmpty()) {
            $hasRed = $evaluations->contains(static fn (DailyWorktimeEvaluation $item): bool => $item->traffic_light === 'red');
            $hasYellow = $evaluations->contains(static fn (DailyWorktimeEvaluation $item): bool => $item->traffic_light === 'yellow');
            $hasGreen = $evaluations->contains(static fn (DailyWorktimeEvaluation $item): bool => $item->traffic_light === 'green');

            if ($hasRed) {
                $trafficLight = 'red';
            } elseif ($hasYellow) {
                $trafficLight = 'yellow';
            } elseif ($hasGreen) {
                $trafficLight = 'green';
            }
        }

        return [
            'scope' => $scope,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'target_minutes' => $targetMinutes,
            'actual_minutes' => $actualMinutes,
            'vacation_minutes' => $vacationMinutes,
            'sick_leave_minutes' => $sickLeaveMinutes,
            'balance_minutes' => $balanceMinutes,
            'traffic_light' => $trafficLight,
            'evaluations' => $evaluations,
        ];
    }
}
