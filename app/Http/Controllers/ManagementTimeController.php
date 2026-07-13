<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeSummary;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Models\WorkSession;
use App\Support\DateInput;
use App\Services\WorktimeEvaluationRebuildService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ManagementTimeController extends Controller
{
    public function __construct(
        private readonly WorktimeEvaluationRebuildService $worktimeEvaluationRebuildService,
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $request->user();

        $this->authorize('viewManagement', DailyWorktimeEvaluation::class);

        $date = DateInput::resolveDate((string) $request->input('date', now()->toDateString()));

        $usersQuery = User::query()->orderBy('name');

        if (! $actor->hasPermission('time.view.all')) {
            $usersQuery->where('manager_id', $actor->id);
        }

        $users = $usersQuery->get();

        foreach ($users as $user) {
            $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($user, $date, $date);
        }

        $summaries = DailyTimeSummary::query()
            ->whereIn('user_id', $users->modelKeys())
            ->where('work_date', $date)
            ->get()
            ->keyBy('user_id');

        $evaluations = DailyWorktimeEvaluation::query()
            ->whereIn('user_id', $users->modelKeys())
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('user_id');

        return view('management.time.index', [
            'users' => $users,
            'summaries' => $summaries,
            'evaluations' => $evaluations,
            'date' => $date,
        ]);
    }

    public function show(Request $request, User $user, string $date): View
    {
        $date = DateInput::resolveDate($date);

        $this->authorize('viewDay', [DailyWorktimeEvaluation::class, $user]);

        $this->worktimeEvaluationRebuildService->rebuildForUserAndRange($user, $date, $date);

        $summary = DailyTimeSummary::query()
            ->where('user_id', $user->id)
            ->where('work_date', $date)
            ->first();

        $evaluation = DailyWorktimeEvaluation::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $date)
            ->first();

        $sessions = WorkSession::query()
            ->with('breaks')
            ->where('user_id', $user->id)
            ->where('work_date', $date)
            ->orderBy('started_at')
            ->get();

        $canFinalize = $summary !== null
            && $summary->finalized_at === null
            && Gate::allows('finalize', [DailyTimeSummary::class, $user]);

        return view('management.time.show', [
            'user' => $user,
            'summary' => $summary,
            'evaluation' => $evaluation,
            'sessions' => $sessions,
            'date' => $date,
            'canFinalize' => $canFinalize,
        ]);
    }
}
