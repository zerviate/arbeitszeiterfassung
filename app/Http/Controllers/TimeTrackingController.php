<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimeActionRequest;
use App\Models\WorkSession;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class TimeTrackingController extends Controller
{
    public function __construct(private readonly TimeTrackingService $timeTrackingService)
    {
    }

    public function clockIn(TimeActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $session = $this->timeTrackingService->clockIn(
            $user,
            $this->resolveOccurredAt($request),
            $request->input('source', 'web'),
        );

        return response()->json(['data' => $session], 201);
    }

    public function clockOut(TimeActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $session = $this->timeTrackingService->clockOut(
            $user,
            $this->resolveOccurredAt($request),
            $request->input('source', 'web'),
        );

        return response()->json(['data' => $session]);
    }

    public function startBreak(TimeActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $break = $this->timeTrackingService->startBreak(
            $user,
            $this->resolveOccurredAt($request),
            $request->input('source', 'web'),
        );

        return response()->json(['data' => $break], 201);
    }

    public function endBreak(TimeActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $break = $this->timeTrackingService->endBreak(
            $user,
            $this->resolveOccurredAt($request),
            $request->input('source', 'web'),
        );

        return response()->json(['data' => $break]);
    }

    private function resolveOccurredAt(TimeActionRequest $request): Carbon
    {
        if (! $request->filled('occurred_at')) {
            return now('UTC');
        }

        return Carbon::parse($request->input('occurred_at'))->utc();
    }
}
