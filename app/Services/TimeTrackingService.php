<?php

namespace App\Services;

use App\Models\BreakSession;
use App\Models\DailyTimeSummary;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeTrackingService
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
        private readonly DailyWorktimeEvaluationService $dailyWorktimeEvaluationService,
        private readonly AuditLogService $auditLogService,
    )
    {
    }

    public function clockIn(User $user, Carbon $time, string $source = 'web'): WorkSession
    {
        $source = $this->normalizeSource($source);
        $occurredAtUtc = $time->copy()->utc();
        $workDate = $this->resolveWorkDate($user, $occurredAtUtc);

        return DB::transaction(function () use ($user, $occurredAtUtc, $workDate, $source): WorkSession {
            $openSession = WorkSession::query()
                ->where('user_id', $user->id)
                ->where('status', WorkSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($openSession) {
                throw ValidationException::withMessages([
                    'clock_in' => 'Es existiert bereits eine offene Arbeitssitzung.',
                ]);
            }

            $this->assertDayIsMutable($user->id, $workDate);

            $event = TimeEvent::query()->create([
                'user_id' => $user->id,
                'type' => TimeEvent::TYPE_CLOCK_IN,
                'occurred_at' => $occurredAtUtc,
                'work_date' => $workDate,
                'source' => $source,
                'created_by' => $user->id,
            ]);

            $session = WorkSession::query()->create([
                'user_id' => $user->id,
                'work_date' => $workDate,
                'started_at' => $occurredAtUtc,
                'status' => WorkSession::STATUS_OPEN,
                'opened_by_event_id' => $event->id,
            ]);

            $this->dailySummaryService->rebuildForUserAndDate($user->id, $workDate);
            $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($user, $workDate);

            $createdSession = $session->fresh();

            $this->auditLogService->log(
                actor: $user,
                event: 'time_tracking.clock_in',
                auditable: $createdSession,
                newValues: $this->sessionSnapshot($createdSession),
                meta: [
                    'time_event_id' => $event->id,
                    'time_event_type' => $event->type,
                ],
                description: 'Clock-in wurde gebucht.',
            );

            return $createdSession;
        });
    }

    public function clockOut(User $user, Carbon $time, string $source = 'web'): WorkSession
    {
        $source = $this->normalizeSource($source);
        $occurredAtUtc = $time->copy()->utc();

        return DB::transaction(function () use ($user, $occurredAtUtc, $source): WorkSession {
            $session = WorkSession::query()
                ->where('user_id', $user->id)
                ->where('status', WorkSession::STATUS_OPEN)
                ->orderBy('started_at')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'clock_out' => 'Es gibt keine offene Arbeitssitzung zum Ausstempeln.',
                ]);
            }

            $this->assertDayIsMutable($user->id, $session->work_date->toDateString());

            $openBreak = BreakSession::query()
                ->where('work_session_id', $session->id)
                ->where('status', BreakSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($openBreak) {
                throw ValidationException::withMessages([
                    'clock_out' => 'Offene Pausen muessen vor dem Ausstempeln beendet werden.',
                ]);
            }

            if ($occurredAtUtc->lessThanOrEqualTo($session->started_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => 'Ausstempeln muss nach dem Arbeitsbeginn liegen.',
                ]);
            }

            $oldSessionState = $this->sessionSnapshot($session);

            $event = TimeEvent::query()->create([
                'user_id' => $user->id,
                'type' => TimeEvent::TYPE_CLOCK_OUT,
                'occurred_at' => $occurredAtUtc,
                'work_date' => $session->work_date->toDateString(),
                'source' => $source,
                'created_by' => $user->id,
            ]);

            $session->forceFill([
                'ended_at' => $occurredAtUtc,
                'gross_minutes' => max(0, $session->started_at->diffInMinutes($occurredAtUtc)),
                'status' => WorkSession::STATUS_CLOSED,
                'closed_by_event_id' => $event->id,
            ])->save();

            $workDate = $session->work_date->toDateString();
            $this->dailySummaryService->rebuildForUserAndDate($user->id, $workDate);
            $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($user, $workDate);

            $updatedSession = $session->fresh();

            $this->auditLogService->log(
                actor: $user,
                event: 'time_tracking.clock_out',
                auditable: $updatedSession,
                oldValues: $oldSessionState,
                newValues: $this->sessionSnapshot($updatedSession),
                meta: [
                    'time_event_id' => $event->id,
                    'time_event_type' => $event->type,
                ],
                description: 'Clock-out wurde gebucht.',
            );

            return $updatedSession;
        });
    }

    public function startBreak(User $user, Carbon $time, string $source = 'web'): BreakSession
    {
        $source = $this->normalizeSource($source);
        $occurredAtUtc = $time->copy()->utc();

        return DB::transaction(function () use ($user, $occurredAtUtc, $source): BreakSession {
            $session = WorkSession::query()
                ->where('user_id', $user->id)
                ->where('status', WorkSession::STATUS_OPEN)
                ->orderBy('started_at')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'break_start' => 'Es gibt keine offene Arbeitssitzung fuer den Pausenstart.',
                ]);
            }

            $this->assertDayIsMutable($user->id, $session->work_date->toDateString());

            $openBreak = BreakSession::query()
                ->where('work_session_id', $session->id)
                ->where('status', BreakSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($openBreak) {
                throw ValidationException::withMessages([
                    'break_start' => 'Es existiert bereits eine offene Pause.',
                ]);
            }

            if ($occurredAtUtc->lessThanOrEqualTo($session->started_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => 'Pausenstart muss nach Arbeitsbeginn liegen.',
                ]);
            }

            $event = TimeEvent::query()->create([
                'user_id' => $user->id,
                'type' => TimeEvent::TYPE_BREAK_START,
                'occurred_at' => $occurredAtUtc,
                'work_date' => $session->work_date->toDateString(),
                'source' => $source,
                'created_by' => $user->id,
            ]);

            $break = BreakSession::query()->create([
                'work_session_id' => $session->id,
                'started_at' => $occurredAtUtc,
                'status' => BreakSession::STATUS_OPEN,
                'started_by_event_id' => $event->id,
            ]);

            $workDate = $session->work_date->toDateString();
            $this->dailySummaryService->rebuildForUserAndDate($user->id, $workDate);
            $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($user, $workDate);

            $createdBreak = $break->fresh();

            $this->auditLogService->log(
                actor: $user,
                event: 'time_tracking.break_start',
                auditable: $createdBreak,
                newValues: $this->breakSnapshot($createdBreak),
                meta: [
                    'time_event_id' => $event->id,
                    'time_event_type' => $event->type,
                    'work_session_id' => $session->id,
                ],
                description: 'Pausenstart wurde gebucht.',
            );

            return $createdBreak;
        });
    }

    public function endBreak(User $user, Carbon $time, string $source = 'web'): BreakSession
    {
        $source = $this->normalizeSource($source);
        $occurredAtUtc = $time->copy()->utc();

        return DB::transaction(function () use ($user, $occurredAtUtc, $source): BreakSession {
            $session = WorkSession::query()
                ->where('user_id', $user->id)
                ->where('status', WorkSession::STATUS_OPEN)
                ->orderBy('started_at')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'break_end' => 'Es gibt keine offene Arbeitssitzung fuer das Pausenende.',
                ]);
            }

            $this->assertDayIsMutable($user->id, $session->work_date->toDateString());

            $break = BreakSession::query()
                ->where('work_session_id', $session->id)
                ->where('status', BreakSession::STATUS_OPEN)
                ->orderBy('started_at')
                ->lockForUpdate()
                ->first();

            if (! $break) {
                throw ValidationException::withMessages([
                    'break_end' => 'Es gibt keine offene Pause zum Beenden.',
                ]);
            }

            if ($occurredAtUtc->lessThanOrEqualTo($break->started_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => 'Pausenende muss nach dem Pausenstart liegen.',
                ]);
            }

            $oldBreakState = $this->breakSnapshot($break);

            $event = TimeEvent::query()->create([
                'user_id' => $user->id,
                'type' => TimeEvent::TYPE_BREAK_END,
                'occurred_at' => $occurredAtUtc,
                'work_date' => $session->work_date->toDateString(),
                'source' => $source,
                'created_by' => $user->id,
            ]);

            $break->forceFill([
                'ended_at' => $occurredAtUtc,
                'minutes' => max(0, $break->started_at->diffInMinutes($occurredAtUtc)),
                'status' => BreakSession::STATUS_CLOSED,
                'ended_by_event_id' => $event->id,
            ])->save();

            $workDate = $session->work_date->toDateString();
            $this->dailySummaryService->rebuildForUserAndDate($user->id, $workDate);
            $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($user, $workDate);

            $updatedBreak = $break->fresh();

            $this->auditLogService->log(
                actor: $user,
                event: 'time_tracking.break_end',
                auditable: $updatedBreak,
                oldValues: $oldBreakState,
                newValues: $this->breakSnapshot($updatedBreak),
                meta: [
                    'time_event_id' => $event->id,
                    'time_event_type' => $event->type,
                    'work_session_id' => $session->id,
                ],
                description: 'Pausenende wurde gebucht.',
            );

            return $updatedBreak;
        });
    }

    private function sessionSnapshot(WorkSession $session): array
    {
        return [
            'id' => $session->id,
            'user_id' => $session->user_id,
            'work_date' => $session->work_date?->toDateString(),
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'gross_minutes' => $session->gross_minutes,
            'status' => $session->status,
            'opened_by_event_id' => $session->opened_by_event_id,
            'closed_by_event_id' => $session->closed_by_event_id,
        ];
    }

    private function breakSnapshot(BreakSession $break): array
    {
        return [
            'id' => $break->id,
            'work_session_id' => $break->work_session_id,
            'started_at' => $break->started_at?->toIso8601String(),
            'ended_at' => $break->ended_at?->toIso8601String(),
            'minutes' => $break->minutes,
            'status' => $break->status,
            'started_by_event_id' => $break->started_by_event_id,
            'ended_by_event_id' => $break->ended_by_event_id,
        ];
    }

    private function resolveWorkDate(User $user, Carbon $timestampUtc): string
    {
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');

        return $timestampUtc->copy()->setTimezone($timezone)->toDateString();
    }

    private function normalizeSource(string $source): string
    {
        $allowedSources = [
            TimeEvent::SOURCE_WEB,
            TimeEvent::SOURCE_MOBILE,
            TimeEvent::SOURCE_TERMINAL,
            TimeEvent::SOURCE_ADMIN,
            TimeEvent::SOURCE_IMPORT,
        ];

        return in_array($source, $allowedSources, true) ? $source : TimeEvent::SOURCE_WEB;
    }

    private function assertDayIsMutable(int $userId, string $workDate): void
    {
        $summary = DailyTimeSummary::query()
            ->where('user_id', $userId)
            ->where('work_date', $workDate)
            ->lockForUpdate()
            ->first();

        if ($summary?->finalized_at !== null) {
            throw ValidationException::withMessages([
                'work_date' => 'Der Arbeitstag ist finalisiert und kann nicht mehr veraendert werden.',
            ]);
        }
    }
}
