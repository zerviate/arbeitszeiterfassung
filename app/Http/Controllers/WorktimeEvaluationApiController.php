<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeSummary;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Services\WorktimeBalanceService;
use App\Services\WorktimeEvaluationRebuildService;
use App\Support\DateInput;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorktimeEvaluationApiController extends Controller
{
    public function __construct(
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
        $targetUser = $this->resolveTargetUser($request, $validated);

        $this->authorize('viewDay', [DailyWorktimeEvaluation::class, $targetUser]);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($targetUser, $workDate, $workDate);

        $evaluation = DailyWorktimeEvaluation::query()
            ->where('user_id', $targetUser->id)
            ->whereDate('work_date', $workDate)
            ->first();

        $summary = DailyTimeSummary::query()
            ->where('user_id', $targetUser->id)
            ->whereDate('work_date', $workDate)
            ->first();

        return response()->json([
            'data' => [
                'user' => $this->userPayload($targetUser),
                'date' => $workDate,
                'evaluation' => $evaluation,
                'summary' => $summary,
            ],
        ]);
    }

    public function week(Request $request, string $date): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $workDate = Carbon::parse(DateInput::resolveDate($date));
        $targetUser = $this->resolveTargetUser($request, $validated);

        $this->authorize('viewWeek', [DailyWorktimeEvaluation::class, $targetUser]);

        $from = $workDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $to = $workDate->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($targetUser, $from, $to);

        $summary = $this->worktimeBalanceService->getWeekSummary($targetUser, $workDate->toDateString());

        return response()->json([
            'data' => [
                'user' => $this->userPayload($targetUser),
                'reference_date' => $workDate->toDateString(),
                'summary' => $summary,
            ],
        ]);
    }

    public function month(Request $request, string $month): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        [$from, $to] = DateInput::resolveMonthRange($month);
        $targetUser = $this->resolveTargetUser($request, $validated);

        $this->authorize('viewMonth', [DailyWorktimeEvaluation::class, $targetUser]);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($targetUser, $from, $to);

        $summary = $this->worktimeBalanceService->getMonthSummary($targetUser, $from);

        return response()->json([
            'data' => [
                'user' => $this->userPayload($targetUser),
                'month' => $month,
                'summary' => $summary,
            ],
        ]);
    }

    private function resolveTargetUser(Request $request, array $validated): User
    {
        if (isset($validated['user_id'])) {
            return User::query()->findOrFail($validated['user_id']);
        }

        return $request->user();
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_number' => $user->employee_number,
            'role' => $user->role,
        ];
    }
}
