<?php

namespace App\Services;

use App\Models\BreakSession;
use App\Models\TimeEvent;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionRebuildService
{
    public function rebuildForUserAndDate(int $userId, Carbon|string $date): void
    {
        $date = Carbon::parse($date)->toDateString();

        DB::transaction(function () use ($userId, $date): void {
            $sessionIds = WorkSession::query()
                ->where('user_id', $userId)
                ->where('work_date', $date)
                ->lockForUpdate()
                ->pluck('id');

            if ($sessionIds->isNotEmpty()) {
                BreakSession::query()->whereIn('work_session_id', $sessionIds)->delete();
            }

            WorkSession::query()
                ->where('user_id', $userId)
                ->where('work_date', $date)
                ->delete();

            $events = TimeEvent::query()
                ->where('user_id', $userId)
                ->where('work_date', $date)
                ->whereNull('invalidated_at')
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $openSession = null;
            $openBreak = null;

            foreach ($events as $event) {
                $resolvedType = $event->resolvedType();

                if (! in_array($resolvedType, [
                    TimeEvent::TYPE_CLOCK_IN,
                    TimeEvent::TYPE_CLOCK_OUT,
                    TimeEvent::TYPE_BREAK_START,
                    TimeEvent::TYPE_BREAK_END,
                ], true)) {
                    continue;
                }

                $occurredAt = $event->occurred_at->copy()->utc();

                if ($resolvedType === TimeEvent::TYPE_CLOCK_IN) {
                    if ($openSession !== null) {
                        throw ValidationException::withMessages([
                            'events' => 'Inkonsistente Reihenfolge: clock_in waehrend eine Session offen ist.',
                        ]);
                    }

                    $openSession = WorkSession::query()->create([
                        'user_id' => $userId,
                        'work_date' => $date,
                        'started_at' => $occurredAt,
                        'status' => WorkSession::STATUS_OPEN,
                        'opened_by_event_id' => $event->id,
                    ]);

                    continue;
                }

                if ($resolvedType === TimeEvent::TYPE_BREAK_START) {
                    if ($openSession === null) {
                        throw ValidationException::withMessages([
                            'events' => 'Inkonsistente Reihenfolge: break_start ohne offene Session.',
                        ]);
                    }

                    if ($openBreak !== null) {
                        throw ValidationException::withMessages([
                            'events' => 'Inkonsistente Reihenfolge: break_start waehrend Pause offen ist.',
                        ]);
                    }

                    if ($occurredAt->lessThanOrEqualTo($openSession->started_at)) {
                        throw ValidationException::withMessages([
                            'events' => 'Inkonsistente Reihenfolge: break_start liegt nicht nach clock_in.',
                        ]);
                    }

                    $openBreak = BreakSession::query()->create([
                        'work_session_id' => $openSession->id,
                        'started_at' => $occurredAt,
                        'status' => BreakSession::STATUS_OPEN,
                        'started_by_event_id' => $event->id,
                    ]);

                    continue;
                }

                if ($resolvedType === TimeEvent::TYPE_BREAK_END) {
                    if ($openBreak === null) {
                        throw ValidationException::withMessages([
                            'events' => 'Inkonsistente Reihenfolge: break_end ohne offene Pause.',
                        ]);
                    }

                    if ($occurredAt->lessThanOrEqualTo($openBreak->started_at)) {
                        throw ValidationException::withMessages([
                            'events' => 'Inkonsistente Reihenfolge: break_end liegt nicht nach break_start.',
                        ]);
                    }

                    $openBreak->forceFill([
                        'ended_at' => $occurredAt,
                        'minutes' => max(0, $openBreak->started_at->diffInMinutes($occurredAt)),
                        'status' => BreakSession::STATUS_CLOSED,
                        'ended_by_event_id' => $event->id,
                    ])->save();

                    $openBreak = null;

                    continue;
                }

                if ($openSession === null) {
                    throw ValidationException::withMessages([
                        'events' => 'Inkonsistente Reihenfolge: clock_out ohne offene Session.',
                    ]);
                }

                if ($openBreak !== null) {
                    throw ValidationException::withMessages([
                        'events' => 'Inkonsistente Reihenfolge: clock_out waehrend Pause offen ist.',
                    ]);
                }

                if ($occurredAt->lessThanOrEqualTo($openSession->started_at)) {
                    throw ValidationException::withMessages([
                        'events' => 'Inkonsistente Reihenfolge: clock_out liegt nicht nach clock_in.',
                    ]);
                }

                $openSession->forceFill([
                    'ended_at' => $occurredAt,
                    'gross_minutes' => max(0, $openSession->started_at->diffInMinutes($occurredAt)),
                    'status' => WorkSession::STATUS_CLOSED,
                    'closed_by_event_id' => $event->id,
                ])->save();

                $openSession = null;
            }
        });
    }
}
