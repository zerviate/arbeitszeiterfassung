<?php

namespace App\Http\Controllers;

use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Services\WorktimeBalanceService;
use App\Services\WorktimeEvaluationRebuildService;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class WorktimeEvaluationWebController extends Controller
{
    public function __construct(
        private readonly WorktimeEvaluationRebuildService $worktimeEvaluationRebuildService,
        private readonly WorktimeBalanceService $worktimeBalanceService,
    ) {
    }

    public function day(User $user, string $date): View
    {
        $workDate = DateInput::resolveDate($date);

        $this->authorize('viewDay', [DailyWorktimeEvaluation::class, $user]);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($user, $workDate, $workDate);

        $evaluation = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->firstOrFail();

        return view('evaluations.day', [
            'user' => $user,
            'date' => $workDate,
            'evaluation' => $evaluation,
        ]);
    }

    public function week(User $user, string $date): View
    {
        $referenceDate = DateInput::resolveDate($date);

        $this->authorize('viewWeek', [DailyWorktimeEvaluation::class, $user]);

        $start = Carbon::parse($referenceDate)->startOfWeek(Carbon::MONDAY)->toDateString();
        $end = Carbon::parse($referenceDate)->endOfWeek(Carbon::SUNDAY)->toDateString();

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($user, $start, $end);

        $summary = $this->worktimeBalanceService->getWeekSummary($user, $referenceDate);

        return view('evaluations.week', [
            'user' => $user,
            'date' => $referenceDate,
            'summary' => $summary,
        ]);
    }

    public function month(User $user, string $month): View
    {
        [$monthStart, $monthEnd] = DateInput::resolveMonthRange($month);

        $this->authorize('viewMonth', [DailyWorktimeEvaluation::class, $user]);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($user, $monthStart, $monthEnd);

        $summary = $this->worktimeBalanceService->getMonthSummary($user, $monthStart);

        return view('evaluations.month', [
            'user' => $user,
            'month' => $month,
            'summary' => $summary,
        ]);
    }
}
