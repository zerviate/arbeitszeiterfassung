<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class WorktimeEvaluationRebuildService
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
        private readonly DailyWorktimeEvaluationService $dailyWorktimeEvaluationService,
    ) {
    }

    public function rebuildForUserAndRange(User $user, Carbon|string $startDate, Carbon|string $endDate): int
    {
        [$start, $end] = $this->resolveRange($startDate, $endDate);

        $count = 0;
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $workDate = $cursor->toDateString();
            $this->dailySummaryService->rebuildForUserAndDate($user->id, $workDate);
            $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($user, $workDate);
            $count++;
            $cursor->addDay();
        }

        return $count;
    }

    public function rebuildForUsersAndRange(iterable $users, Carbon|string $startDate, Carbon|string $endDate): array
    {
        $rebuiltUsers = 0;
        $rebuiltDays = 0;

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $rebuiltDays += $this->rebuildForUserAndRange($user, $startDate, $endDate);
            $rebuiltUsers++;
        }

        return [
            'rebuilt_users' => $rebuiltUsers,
            'rebuilt_days' => $rebuiltDays,
        ];
    }

    public function rebuildForAllUsers(Carbon|string $startDate, Carbon|string $endDate, int $chunkSize = 100): array
    {
        $chunkSize = max(1, $chunkSize);
        $totals = [
            'rebuilt_users' => 0,
            'rebuilt_days' => 0,
        ];

        User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById($chunkSize, function (EloquentCollection $users) use (&$totals, $startDate, $endDate): void {
                $result = $this->rebuildForUsersAndRange($users, $startDate, $endDate);
                $totals['rebuilt_users'] += $result['rebuilt_users'];
                $totals['rebuilt_days'] += $result['rebuilt_days'];
            });

        return $totals;
    }

    private function resolveRange(Carbon|string $startDate, Carbon|string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }
}
