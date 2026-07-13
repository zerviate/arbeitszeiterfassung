<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeSummary;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Services\ComplianceCheckService;
use App\Services\WorktimeBalanceService;
use App\Services\WorktimeEvaluationRebuildService;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeOverviewController extends Controller
{
    public function __construct(
        private readonly ComplianceCheckService $complianceCheckService,
        private readonly WorktimeBalanceService $worktimeBalanceService,
        private readonly WorktimeEvaluationRebuildService $worktimeEvaluationRebuildService,
    ) {
    }

    public function day(Request $request, string $date): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $workDate = DateInput::resolveDate($date);
        $targetUser = isset($validated['user_id'])
            ? User::query()->findOrFail($validated['user_id'])
            : $request->user();

        $this->authorize('viewDay', [DailyWorktimeEvaluation::class, $targetUser]);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($targetUser, $workDate, $workDate);

        $summary = DailyTimeSummary::query()
            ->where('user_id', $targetUser->id)
            ->whereDate('work_date', $workDate)
            ->first();

        $evaluation = DailyWorktimeEvaluation::query()
            ->where('user_id', $targetUser->id)
            ->whereDate('work_date', $workDate)
            ->first();
        $flags = $this->complianceCheckService->evaluate($targetUser, $workDate);
        $sessions = $targetUser->workSessions()
            ->with('breaks')
            ->where('work_date', $workDate)
            ->orderBy('started_at')
            ->get();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'employee_number' => $targetUser->employee_number,
                    'role' => $targetUser->role,
                ],
                'date' => $workDate,
                'summary' => $summary,
                'evaluation' => $evaluation,
                'violation_flags' => $flags,
                'sessions' => $sessions,
            ],
        ]);
    }

    public function month(Request $request, string $month): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $targetUser = isset($validated['user_id'])
            ? User::query()->findOrFail($validated['user_id'])
            : $request->user();

        $this->authorize('viewMonth', [DailyWorktimeEvaluation::class, $targetUser]);

        [$from, $to] = DateInput::resolveMonthRange($month);
        $start = Carbon::parse($from, 'UTC')->startOfDay();
        $end = Carbon::parse($to, 'UTC')->startOfDay();

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange(
            $targetUser,
            $start->toDateString(),
            $end->toDateString(),
        );

        $summaries = DailyTimeSummary::query()
            ->where('user_id', $targetUser->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

        $evaluations = DailyWorktimeEvaluation::query()
            ->where('user_id', $targetUser->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

        $evaluationTotals = $this->worktimeBalanceService->getMonthSummary($targetUser, $start->toDateString());

        return response()->json([
            'data' => [
                'month' => $month,
                'summaries' => $summaries,
                'evaluations' => $evaluations,
                'totals' => [
                    'gross_minutes' => (int) $summaries->sum('gross_minutes'),
                    'break_minutes' => (int) $summaries->sum('break_minutes'),
                    'net_minutes' => (int) $summaries->sum('net_minutes'),
                    'overtime_minutes' => (int) $summaries->sum('overtime_minutes'),
                ],
                'evaluation_totals' => [
                    'target_minutes' => $evaluationTotals['target_minutes'],
                    'actual_minutes' => $evaluationTotals['actual_minutes'],
                    'vacation_minutes' => $evaluationTotals['vacation_minutes'],
                    'sick_leave_minutes' => $evaluationTotals['sick_leave_minutes'],
                    'balance_minutes' => $evaluationTotals['balance_minutes'],
                    'traffic_light' => $evaluationTotals['traffic_light'],
                ],
            ],
        ]);
    }
}
