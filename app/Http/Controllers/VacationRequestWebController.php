<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewVacationRequest;
use App\Http\Requests\StoreVacationRequest;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\VacationApprovalService;
use App\Services\VacationBalanceService;
use App\Services\VacationRequestService;
use App\Support\VisibleUserScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VacationRequestWebController extends Controller
{
    public function __construct(
        private readonly VacationRequestService $vacationRequestService,
        private readonly VacationApprovalService $vacationApprovalService,
        private readonly VacationBalanceService $vacationBalanceService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AbsenceRequest::class);

        $actor = $request->user();
        $visibleUserIds = VisibleUserScope::absenceIds($actor);
        $showVacationSummary = ! $actor->hasAnyPermission([
            'absence.view.team',
            'absence.view.all',
        ]);

        $requests = AbsenceRequest::query()
            ->with(['user', 'requestedBy', 'reviewedBy'])
            ->where('type', AbsenceRequest::TYPE_VACATION)
            ->whereIn('user_id', $visibleUserIds)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $summaryYear = (int) now()->setTimezone($actor->timezone ?: 'UTC')->format('Y');
        $vacationSummary = $showVacationSummary
            ? $this->vacationBalanceService->getYearSummary(
                user: $actor,
                year: $summaryYear,
            )
            : null;

        return view('vacations.index', [
            'requests' => $requests,
            'vacationSummary' => $vacationSummary,
            'showVacationSummary' => $showVacationSummary,
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $request->user();

        $this->authorize('create', [AbsenceRequest::class, $actor]);

        $users = collect();

        if ($actor->hasPermission('absence.request.for_others')) {
            if ($actor->hasPermission('absence.view.all')) {
                $users = User::query()->orderBy('name')->get();
            } elseif ($actor->hasPermission('absence.view.team')) {
                $users = $actor->teamMembers()->orderBy('name')->get();
            }
        }

        return view('vacations.create', [
            'users' => $users,
        ]);
    }

    public function store(StoreVacationRequest $request): RedirectResponse
    {
        $actor = $request->user();

        $employee = $request->filled('user_id')
            ? User::query()->findOrFail($request->integer('user_id'))
            : $actor;

        $this->authorize('create', [AbsenceRequest::class, $employee]);

        $this->vacationRequestService->createRequest(
            employee: $employee,
            requestedBy: $actor,
            startDate: $request->string('start_date')->toString(),
            endDate: $request->string('end_date')->toString(),
            reason: $request->input('reason'),
        );

        return redirect()->route('vacations.index')
            ->with('success', 'Urlaubsantrag wurde angelegt.');
    }

    public function show(Request $request, AbsenceRequest $vacation): View
    {
        $vacation->load(['user', 'requestedBy', 'reviewedBy', 'cancelledBy', 'records']);

        $this->authorize('view', $vacation);

        $actor = $request->user();
        $isPending = $vacation->status === AbsenceRequest::STATUS_PENDING;
        $canReview = Gate::allows('review', $vacation);
        $canCancel = Gate::allows('cancel', $vacation);
        $canSeeCancelAction = $actor->hasRole('admin')
            || ($actor->id === $vacation->user_id && $actor->hasPermission('absence.cancel.own'))
            || $canReview;

        $vacationSummary = $this->vacationBalanceService->getYearSummary(
            user: $vacation->user,
            year: (int) ($vacation->start_date?->format('Y') ?? now('UTC')->format('Y')),
        );

        return view('vacations.show', [
            'vacation' => $vacation,
            'isPending' => $isPending,
            'canReview' => $canReview,
            'canCancel' => $canCancel,
            'canSeeCancelAction' => $canSeeCancelAction,
            'vacationSummary' => $vacationSummary,
        ]);
    }

    public function approve(ReviewVacationRequest $request, AbsenceRequest $vacation): RedirectResponse
    {
        $this->authorize('review', $vacation);

        $this->vacationApprovalService->approve(
            absenceRequest: $vacation,
            reviewer: $request->user(),
            reviewNote: $request->input('review_note'),
        );

        return back()->with('success', 'Urlaubsantrag wurde genehmigt.');
    }

    public function reject(ReviewVacationRequest $request, AbsenceRequest $vacation): RedirectResponse
    {
        $this->authorize('review', $vacation);

        $this->vacationApprovalService->reject(
            absenceRequest: $vacation,
            reviewer: $request->user(),
            reviewNote: $request->input('review_note'),
        );

        return back()->with('success', 'Urlaubsantrag wurde abgelehnt.');
    }

    public function cancel(Request $request, AbsenceRequest $vacation): RedirectResponse
    {
        $this->authorize('cancel', $vacation);

        $this->vacationRequestService->cancelRequest(
            absenceRequest: $vacation,
            actor: $request->user(),
        );

        return back()->with('success', 'Urlaubsantrag wurde storniert.');
    }
}
