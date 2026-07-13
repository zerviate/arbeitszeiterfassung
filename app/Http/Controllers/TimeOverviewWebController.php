<?php

namespace App\Http\Controllers;

use App\Models\DailyWorktimeEvaluation;
use App\Models\DailyTimeSummary;
use App\Models\WorkSession;
use App\Services\DailySummaryService;
use App\Services\DailyWorktimeEvaluationService;
use App\Services\WorktimeBalanceService;
use App\Services\WorktimeEvaluationRebuildService;
use App\Support\DateInput;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TimeOverviewWebController extends Controller
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
        private readonly DailyWorktimeEvaluationService $dailyWorktimeEvaluationService,
        private readonly WorktimeBalanceService $worktimeBalanceService,
        private readonly WorktimeEvaluationRebuildService $worktimeEvaluationRebuildService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');
        $today = now()->setTimezone($timezone)->toDateString();

        $summary = $this->dailySummaryService->rebuildForUserAndDate($user->id, $today);
        $todayEvaluation = $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($user, $today);

        $openSession = WorkSession::query()
            ->with('breaks')
            ->where('user_id', $user->id)
            ->where('status', WorkSession::STATUS_OPEN)
            ->orderBy('started_at')
            ->first();

        $month = now()->setTimezone($timezone)->format('Y-m');
        [$from, $to] = DateInput::resolveMonthRange($month);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($user, $from, $to);

        $monthSummaries = DailyTimeSummary::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $monthSummariesByDate = $monthSummaries->keyBy(fn (DailyTimeSummary $item): ?string => $item->work_date?->toDateString());

        $monthEvaluations = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $monthBalance = $this->worktimeBalanceService->getMonthSummary($user, $from);

        return view('time.index', [
            'summary' => $summary,
            'evaluation' => $todayEvaluation,
            'openSession' => $openSession,
            'monthSummaries' => $monthSummaries,
            'monthSummariesByDate' => $monthSummariesByDate,
            'monthEvaluations' => $monthEvaluations,
            'monthBalance' => $monthBalance,
            'today' => $today,
            'month' => $month,
        ]);
    }

    public function day(Request $request, string $date): View
    {
        $user = $request->user();
        $workDate = DateInput::resolveDate($date);

        $this->authorize('viewDay', [DailyWorktimeEvaluation::class, $user]);

        $summary = $this->dailySummaryService->rebuildForUserAndDate($user->id, $workDate);
        $evaluation = $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($user, $workDate);

        $sessions = WorkSession::query()
            ->with('breaks')
            ->where('user_id', $user->id)
            ->where('work_date', $workDate)
            ->orderBy('started_at')
            ->get();

        return view('time.day', [
            'summary' => $summary,
            'evaluation' => $evaluation,
            'sessions' => $sessions,
            'date' => $workDate,
        ]);
    }

    public function month(Request $request, ?string $month = null): View
    {
        $user = $request->user();

        if ($month === null) {
            $timezone = $user->timezone ?: config('app.timezone', 'UTC');
            $month = now()->setTimezone($timezone)->format('Y-m');
        }

        $this->authorize('viewMonth', [DailyWorktimeEvaluation::class, $user]);

        [$from, $to] = DateInput::resolveMonthRange($month);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($user, $from, $to);

        $summaries = DailyTimeSummary::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $summariesByDate = $summaries->keyBy(fn (DailyTimeSummary $item): ?string => $item->work_date?->toDateString());

        $evaluations = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $monthBalance = $this->worktimeBalanceService->getMonthSummary($user, $from);

        return view('time.month', [
            'summaries' => $summaries,
            'summariesByDate' => $summariesByDate,
            'evaluations' => $evaluations,
            'monthBalance' => $monthBalance,
            'month' => $month,
            'from' => $from,
            'to' => $to,
        ]);
    }

}
