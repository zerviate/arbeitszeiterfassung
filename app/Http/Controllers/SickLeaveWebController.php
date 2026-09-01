<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSickLeaveRequest;
use App\Http\Requests\UpdateSickLeaveRequest;
use App\Models\SickLeaveGroup;
use App\Models\User;
use App\Services\SickLeaveService;
use App\Support\DateInput;
use App\Support\VisibleUserScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SickLeaveWebController extends Controller
{
    public function __construct(
        private readonly SickLeaveService $sickLeaveService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SickLeaveGroup::class);

        $actor = $request->user();
        $visibleUserIds = VisibleUserScope::absenceIds($actor);

        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $selectedMonth = isset($validated['month']) && $validated['month'] !== ''
            ? (string) $validated['month']
            : now()->setTimezone($actor->timezone ?: 'UTC')->format('Y-m');

        [$monthStart, $monthEnd] = DateInput::resolveMonthRange($selectedMonth);

        $groupsQuery = SickLeaveGroup::query()
            ->with(['user', 'recordedBy'])
            ->withCount('records')
            ->whereIn('user_id', $visibleUserIds)
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart);

        $groups = $groupsQuery
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $monthGroupCount = SickLeaveGroup::query()
            ->whereIn('user_id', $visibleUserIds)
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->count();

        $totalGroupCount = SickLeaveGroup::query()
            ->whereIn('user_id', $visibleUserIds)
            ->count();

        return view('sick-leaves.index', [
            'groups' => $groups,
            'selectedMonth' => $selectedMonth,
            'metricMonth' => $selectedMonth,
            'monthGroupCount' => $monthGroupCount,
            'totalGroupCount' => $totalGroupCount,
            'exportMonth' => $selectedMonth,
            'canManage' => Gate::allows('create', SickLeaveGroup::class),
            'canExport' => $actor->hasRole('admin'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SickLeaveGroup::class);

        $actor = $request->user();

        if ($actor->hasPermission('absence.view.all')) {
            $users = User::query()->orderBy('name')->get();
        } elseif ($actor->hasPermission('absence.view.team')) {
            $users = $actor->teamMembers()
                ->orderBy('name')
                ->get()
                ->push($actor)
                ->unique('id')
                ->values();
        } else {
            $users = User::query()->whereKey($actor->id)->get();
        }

        return view('sick-leaves.create', [
            'users' => $users,
        ]);
    }

    public function store(StoreSickLeaveRequest $request): RedirectResponse
    {
        $this->authorize('create', SickLeaveGroup::class);

        $employee = User::query()->findOrFail($request->integer('user_id'));

        $sickLeaveGroup = $this->sickLeaveService->create(
            employee: $employee,
            actor: $request->user(),
            startDate: $request->string('start_date')->toString(),
            endDate: $request->string('end_date')->toString(),
            note: $request->input('note'),
        );

        return redirect()
            ->route('sick-leaves.show', $sickLeaveGroup)
            ->with('success', 'Krankmeldung wurde angelegt.');
    }

    public function show(SickLeaveGroup $sickLeaveGroup): View
    {
        $sickLeaveGroup->load(['user', 'recordedBy']);

        $this->authorize('view', $sickLeaveGroup);

        $records = $sickLeaveGroup->records()
            ->orderBy('absence_date')
            ->get();

        return view('sick-leaves.show', [
            'sickLeaveGroup' => $sickLeaveGroup,
            'records' => $records,
            'canManage' => Gate::allows('update', $sickLeaveGroup),
        ]);
    }

    public function edit(SickLeaveGroup $sickLeaveGroup): View
    {
        $sickLeaveGroup->load(['user', 'recordedBy']);

        $this->authorize('update', $sickLeaveGroup);

        return view('sick-leaves.edit', [
            'sickLeaveGroup' => $sickLeaveGroup,
        ]);
    }

    public function update(UpdateSickLeaveRequest $request, SickLeaveGroup $sickLeaveGroup): RedirectResponse
    {
        $this->authorize('update', $sickLeaveGroup);

        $updatedGroup = $this->sickLeaveService->updateGroup(
            sickLeaveGroup: $sickLeaveGroup,
            actor: $request->user(),
            startDate: $request->string('start_date')->toString(),
            endDate: $request->string('end_date')->toString(),
            note: $request->input('note'),
        );

        return redirect()
            ->route('sick-leaves.show', $updatedGroup)
            ->with('success', 'Krankmeldung wurde aktualisiert.');
    }

    public function destroy(Request $request, SickLeaveGroup $sickLeaveGroup): RedirectResponse
    {
        $this->authorize('delete', $sickLeaveGroup);

        $this->sickLeaveService->deleteGroup(
            sickLeaveGroup: $sickLeaveGroup,
            actor: $request->user(),
        );

        return redirect()
            ->route('sick-leaves.index')
            ->with('success', 'Krankmeldung wurde geloescht.');
    }
}
