<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewTimeCorrectionRequest;
use App\Http\Requests\StoreTimeCorrectionRequest;
use App\Models\TimeCorrection;
use App\Models\TimeEvent;
use App\Models\User;
use App\Services\TimeCorrectionService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimeCorrectionWebController extends Controller
{
    public function __construct(
        private readonly TimeCorrectionService $timeCorrectionService,
    ) {
    }

    public function index(Request $request): View
    {
        $actor = $request->user();

        $this->authorize('viewAny', TimeCorrection::class);

        $query = TimeCorrection::query()
            ->with(['user', 'requestedBy', 'reviewedBy'])
            ->orderByDesc('created_at');

        if ($actor->hasPermission('time.view.all')) {
            // no restriction
        } elseif ($actor->hasPermission('time.view.team')) {
            $teamIds = $actor->teamMembers()->pluck('id');
            $query->whereIn('user_id', $teamIds->push($actor->id)->unique()->values());
        } else {
            $query->where('user_id', $actor->id);
        }

        $corrections = $query->paginate(20)->withQueryString();

        return view('time.corrections.index', [
            'corrections' => $corrections,
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        $this->authorize('request', [TimeCorrection::class, $actor]);

        $users = collect();

        if ($actor->hasPermission('time.correct.request.for_others')) {
            if ($actor->hasPermission('time.view.all')) {
                $users = User::query()->orderBy('name')->get();
            } elseif ($actor->hasPermission('time.view.team')) {
                $users = $actor->teamMembers()->orderBy('name')->get();
            }
        }

        return view('time.corrections.create', [
            'users' => $users,
        ]);
    }

    public function store(StoreTimeCorrectionRequest $request): RedirectResponse
    {
        $actor = $request->user();

        $targetUser = $request->filled('user_id')
            ? User::query()->findOrFail($request->integer('user_id'))
            : $actor;

        $this->authorize('request', [TimeCorrection::class, $targetUser]);

        $timezone = $targetUser->timezone ?: config('app.timezone', 'UTC');
        $events = $this->buildEventsFromSessions(
            sessions: $request->input('sessions', []),
            timezone: $timezone,
        );

        $this->timeCorrectionService->requestCorrection(
            targetUser: $targetUser,
            requestedBy: $actor,
            workDate: $request->string('work_date')->toString(),
            newValues: ['events' => $events],
            reason: $request->string('reason')->toString(),
        );

        return redirect()->route('time.corrections.index')
            ->with('success', 'Korrekturantrag angelegt.');
    }

    public function show(TimeCorrection $correction): View
    {
        $correction->load(['user', 'requestedBy', 'reviewedBy']);

        $this->authorize('view', $correction);

        return view('time.corrections.show', [
            'correction' => $correction,
        ]);
    }

    public function approve(ReviewTimeCorrectionRequest $request, TimeCorrection $correction): RedirectResponse
    {
        $this->authorize('review', $correction);

        $this->timeCorrectionService->approveCorrection(
            correction: $correction,
            reviewer: $request->user(),
            reviewNote: $request->input('review_note'),
        );

        return back()->with('success', 'Korrekturantrag genehmigt.');
    }

    public function reject(ReviewTimeCorrectionRequest $request, TimeCorrection $correction): RedirectResponse
    {
        $this->authorize('review', $correction);

        $this->timeCorrectionService->rejectCorrection(
            correction: $correction,
            reviewer: $request->user(),
            reviewNote: $request->input('review_note'),
        );

        return back()->with('success', 'Korrekturantrag abgelehnt.');
    }

    private function buildEventsFromSessions(array $sessions, string $timezone): array
    {
        if ($sessions === []) {
            throw ValidationException::withMessages([
                'sessions' => 'Es muss mindestens eine Session angegeben werden.',
            ]);
        }

        $events = [];

        foreach ($sessions as $sessionIndex => $sessionData) {
            if (! is_array($sessionData)) {
                throw ValidationException::withMessages([
                    "sessions.$sessionIndex" => 'Session muss ein Objekt sein.',
                ]);
            }

            $sessionStartedAt = $this->parseDateTime(
                value: $sessionData['started_at'] ?? null,
                field: "sessions.$sessionIndex.started_at",
                timezone: $timezone,
            );

            $sessionEndedAt = $this->parseDateTime(
                value: $sessionData['ended_at'] ?? null,
                field: "sessions.$sessionIndex.ended_at",
                timezone: $timezone,
            );

            if ($sessionEndedAt->lessThanOrEqualTo($sessionStartedAt)) {
                throw ValidationException::withMessages([
                    "sessions.$sessionIndex.ended_at" => 'Session-Ende muss nach Session-Beginn liegen.',
                ]);
            }

            $events[] = $this->buildEventPayload(
                type: TimeEvent::TYPE_CLOCK_IN,
                occurredAt: $sessionStartedAt,
                sessionIndex: (int) $sessionIndex,
            );

            $breaks = $sessionData['breaks'] ?? [];

            if ($breaks === null) {
                $breaks = [];
            }

            if (! is_array($breaks)) {
                throw ValidationException::withMessages([
                    "sessions.$sessionIndex.breaks" => 'breaks muss ein Array sein.',
                ]);
            }

            foreach ($breaks as $breakIndex => $breakData) {
                if (! is_array($breakData)) {
                    throw ValidationException::withMessages([
                        "sessions.$sessionIndex.breaks.$breakIndex" => 'Pause muss ein Objekt sein.',
                    ]);
                }

                $breakStartedAt = $this->parseDateTime(
                    value: $breakData['started_at'] ?? null,
                    field: "sessions.$sessionIndex.breaks.$breakIndex.started_at",
                    timezone: $timezone,
                );

                $breakEndedAt = $this->parseDateTime(
                    value: $breakData['ended_at'] ?? null,
                    field: "sessions.$sessionIndex.breaks.$breakIndex.ended_at",
                    timezone: $timezone,
                );

                if ($breakEndedAt->lessThanOrEqualTo($breakStartedAt)) {
                    throw ValidationException::withMessages([
                        "sessions.$sessionIndex.breaks.$breakIndex.ended_at" => 'Pausenende muss nach Pausenbeginn liegen.',
                    ]);
                }

                if ($breakStartedAt->lessThanOrEqualTo($sessionStartedAt) || $breakStartedAt->greaterThanOrEqualTo($sessionEndedAt)) {
                    throw ValidationException::withMessages([
                        "sessions.$sessionIndex.breaks.$breakIndex.started_at" => 'Pausenbeginn muss innerhalb der Session liegen.',
                    ]);
                }

                if ($breakEndedAt->greaterThan($sessionEndedAt)) {
                    throw ValidationException::withMessages([
                        "sessions.$sessionIndex.breaks.$breakIndex.ended_at" => 'Pausenende muss innerhalb der Session liegen.',
                    ]);
                }

                $events[] = $this->buildEventPayload(
                    type: TimeEvent::TYPE_BREAK_START,
                    occurredAt: $breakStartedAt,
                    sessionIndex: (int) $sessionIndex,
                    breakIndex: (int) $breakIndex,
                );

                $events[] = $this->buildEventPayload(
                    type: TimeEvent::TYPE_BREAK_END,
                    occurredAt: $breakEndedAt,
                    sessionIndex: (int) $sessionIndex,
                    breakIndex: (int) $breakIndex,
                );
            }

            $events[] = $this->buildEventPayload(
                type: TimeEvent::TYPE_CLOCK_OUT,
                occurredAt: $sessionEndedAt,
                sessionIndex: (int) $sessionIndex,
            );
        }

        $priority = [
            TimeEvent::TYPE_CLOCK_IN => 0,
            TimeEvent::TYPE_BREAK_START => 1,
            TimeEvent::TYPE_BREAK_END => 2,
            TimeEvent::TYPE_CLOCK_OUT => 3,
        ];

        usort($events, static function (array $left, array $right) use ($priority): int {
            $leftSort = [$left['occurred_at'], $priority[$left['type']] ?? 99];
            $rightSort = [$right['occurred_at'], $priority[$right['type']] ?? 99];

            return $leftSort <=> $rightSort;
        });

        return $events;
    }

    private function buildEventPayload(string $type, Carbon $occurredAt, int $sessionIndex, ?int $breakIndex = null): array
    {
        $meta = ['session_index' => $sessionIndex];

        if ($breakIndex !== null) {
            $meta['break_index'] = $breakIndex;
        }

        return [
            'type' => $type,
            'occurred_at' => $occurredAt->toIso8601String(),
            'source' => TimeEvent::SOURCE_WEB,
            'meta' => $meta,
        ];
    }

    private function parseDateTime(mixed $value, string $field, string $timezone): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([
                $field => 'Zeitpunkt ist erforderlich.',
            ]);
        }

        try {
            return Carbon::parse($value, $timezone)->utc();
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                $field => 'Zeitpunkt ist ungueltig.',
            ]);
        }
    }
}
