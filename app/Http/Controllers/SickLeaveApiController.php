<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSickLeaveRequest;
use App\Http\Requests\UpdateSickLeaveRequest;
use App\Models\SickLeaveGroup;
use App\Models\User;
use App\Services\SickLeaveService;
use App\Support\DateInput;
use App\Support\VisibleUserScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SickLeaveApiController extends Controller
{
    public function __construct(
        private readonly SickLeaveService $sickLeaveService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SickLeaveGroup::class);

        $actor = $request->user();
        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $visibleUserIds = VisibleUserScope::absenceIds($actor);
        $query = SickLeaveGroup::query()
            ->with(['user', 'recordedBy'])
            ->whereIn('user_id', $visibleUserIds);

        if (isset($validated['month'])) {
            [$monthStart, $monthEnd] = DateInput::resolveMonthRange($validated['month']);

            $query
                ->whereDate('start_date', '<=', $monthEnd)
                ->whereDate('end_date', '>=', $monthStart);
        }

        if (isset($validated['user_id']) && $visibleUserIds->contains((int) $validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        return response()->json([
            'data' => $query
                ->withCount('records')
                ->orderByDesc('start_date')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function show(SickLeaveGroup $sickLeaveGroup): JsonResponse
    {
        $this->authorize('view', $sickLeaveGroup);

        $sickLeaveGroup->load(['user', 'recordedBy', 'records']);

        return response()->json([
            'data' => $sickLeaveGroup,
        ]);
    }

    public function store(StoreSickLeaveRequest $request): JsonResponse
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

        return response()->json([
            'data' => $sickLeaveGroup->load(['user', 'recordedBy', 'records']),
        ], 201);
    }

    public function update(UpdateSickLeaveRequest $request, SickLeaveGroup $sickLeaveGroup): JsonResponse
    {
        $this->authorize('update', $sickLeaveGroup);

        $updatedGroup = $this->sickLeaveService->updateGroup(
            sickLeaveGroup: $sickLeaveGroup,
            actor: $request->user(),
            startDate: $request->string('start_date')->toString(),
            endDate: $request->string('end_date')->toString(),
            note: $request->input('note'),
        );

        return response()->json([
            'data' => $updatedGroup->load(['user', 'recordedBy', 'records']),
        ]);
    }

    public function destroy(Request $request, SickLeaveGroup $sickLeaveGroup): Response
    {
        $this->authorize('delete', $sickLeaveGroup);

        $this->sickLeaveService->deleteGroup(
            sickLeaveGroup: $sickLeaveGroup,
            actor: $request->user(),
        );

        return response()->noContent();
    }
}
