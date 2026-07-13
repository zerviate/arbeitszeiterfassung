<?php

namespace App\Http\Controllers;

use App\Models\TimeCorrection;
use App\Models\User;
use App\Services\TimeCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeCorrectionController extends Controller
{
    public function __construct(private readonly TimeCorrectionService $timeCorrectionService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'work_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'old_values' => ['nullable', 'array'],
            'new_values' => ['required', 'array'],
            'new_values.events' => ['required', 'array', 'min:1'],
            'new_values.events.*.type' => ['required', 'string', 'in:clock_in,clock_out,break_start,break_end'],
            'new_values.events.*.occurred_at' => ['required', 'date'],
            'new_values.events.*.source' => ['nullable', 'string'],
            'new_values.events.*.reason' => ['nullable', 'string', 'max:500'],
            'new_values.events.*.meta' => ['nullable', 'array'],
        ]);

        $actor = $request->user();
        $targetUser = isset($validated['user_id'])
            ? User::query()->findOrFail($validated['user_id'])
            : $actor;

        $this->authorize('request', [TimeCorrection::class, $targetUser]);

        $correction = $this->timeCorrectionService->requestCorrection(
            targetUser: $targetUser,
            requestedBy: $actor,
            workDate: $validated['work_date'],
            newValues: $validated['new_values'],
            reason: $validated['reason'],
            oldValues: $validated['old_values'] ?? null,
        );

        return response()->json(['data' => $correction], 201);
    }

    public function approve(Request $request, TimeCorrection $correction): JsonResponse
    {
        $this->authorize('review', $correction);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $approvedCorrection = $this->timeCorrectionService->approveCorrection(
            correction: $correction,
            reviewer: $request->user(),
            reviewNote: $validated['review_note'] ?? null,
        );

        return response()->json(['data' => $approvedCorrection]);
    }

    public function reject(Request $request, TimeCorrection $correction): JsonResponse
    {
        $this->authorize('review', $correction);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $rejectedCorrection = $this->timeCorrectionService->rejectCorrection(
            correction: $correction,
            reviewer: $request->user(),
            reviewNote: $validated['review_note'] ?? null,
        );

        return response()->json(['data' => $rejectedCorrection]);
    }
}
