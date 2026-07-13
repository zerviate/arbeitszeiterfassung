<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimeActionRequest;
use App\Models\WorkSession;
use App\Services\TimeTrackingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class TimeTrackingWebController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $timeTrackingService,
    ) {
    }

    public function clockIn(TimeActionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $this->timeTrackingService->clockIn(
            user: $user,
            time: $this->resolveOccurredAt($request),
            source: (string) $request->input('source', 'web'),
        );

        return back()->with('toast', [
            'message' => 'Arbeitszeit gestartet.',
            'variant' => 'success',
        ]);
    }

    public function clockOut(TimeActionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $this->timeTrackingService->clockOut(
            user: $user,
            time: $this->resolveOccurredAt($request),
            source: (string) $request->input('source', 'web'),
        );

        return back()->with('toast', [
            'message' => 'Arbeitszeit beendet.',
            'variant' => 'neutral',
        ]);
    }

    public function breakStart(TimeActionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $this->timeTrackingService->startBreak(
            user: $user,
            time: $this->resolveOccurredAt($request),
            source: (string) $request->input('source', 'web'),
        );

        return back()->with('toast', [
            'message' => 'Pause gestartet.',
            'variant' => 'warning',
        ]);
    }

    public function breakEnd(TimeActionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('track', [WorkSession::class, $user]);

        $this->timeTrackingService->endBreak(
            user: $user,
            time: $this->resolveOccurredAt($request),
            source: (string) $request->input('source', 'web'),
        );

        return back()->with('toast', [
            'message' => 'Pause beendet.',
            'variant' => 'success',
        ]);
    }

    private function resolveOccurredAt(TimeActionRequest $request): Carbon
    {
        if (! $request->filled('occurred_at')) {
            return now('UTC');
        }

        return Carbon::parse($request->input('occurred_at'))->utc();
    }
}
