<?php

namespace App\Services;

use App\Models\HolidayCalendarEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HolidayCalendarManagementService
{
    public function __construct(
        private readonly WorktimeEvaluationRebuildService $worktimeEvaluationRebuildService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function create(User $actor, array $payload): HolidayCalendarEntry
    {
        $holidayDate = $this->resolveDate($payload['holiday_date']);

        return DB::transaction(function () use ($actor, $payload, $holidayDate): HolidayCalendarEntry {
            $this->assertDateIsUnique($holidayDate, null);

            $entry = HolidayCalendarEntry::query()->create([
                'holiday_date' => $holidayDate,
                'name' => trim((string) $payload['name']),
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'created_by' => $actor->id,
            ]);

            if ($entry->is_active) {
                $this->worktimeEvaluationRebuildService->rebuildForAllUsers($holidayDate, $holidayDate);
            }

            $this->auditLogService->log(
                actor: $actor,
                event: 'holiday_calendar_entry.created',
                auditable: $entry,
                newValues: $this->snapshot($entry),
                description: 'Feiertagseintrag wurde erstellt.',
            );

            return $entry;
        });
    }

    public function update(User $actor, HolidayCalendarEntry $entry, array $payload): HolidayCalendarEntry
    {
        $holidayDate = $this->resolveDate($payload['holiday_date']);

        return DB::transaction(function () use ($actor, $entry, $payload, $holidayDate): HolidayCalendarEntry {
            $lockedEntry = HolidayCalendarEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDateIsUnique($holidayDate, $lockedEntry->id);

            $before = $this->snapshot($lockedEntry);
            $oldDate = $lockedEntry->holiday_date?->toDateString();
            $oldActive = (bool) $lockedEntry->is_active;

            $lockedEntry->forceFill([
                'holiday_date' => $holidayDate,
                'name' => trim((string) $payload['name']),
                'is_active' => (bool) ($payload['is_active'] ?? false),
            ])->save();

            $affectedDates = collect([$oldDate, $holidayDate])
                ->filter()
                ->unique()
                ->values();

            if ($oldActive || $lockedEntry->is_active) {
                foreach ($affectedDates as $affectedDate) {
                    $this->worktimeEvaluationRebuildService->rebuildForAllUsers($affectedDate, $affectedDate);
                }
            }

            $this->auditLogService->log(
                actor: $actor,
                event: 'holiday_calendar_entry.updated',
                auditable: $lockedEntry,
                oldValues: $before,
                newValues: $this->snapshot($lockedEntry),
                description: 'Feiertagseintrag wurde aktualisiert.',
            );

            return $lockedEntry->fresh();
        });
    }

    public function delete(User $actor, HolidayCalendarEntry $entry): void
    {
        DB::transaction(function () use ($actor, $entry): void {
            $lockedEntry = HolidayCalendarEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldValues = $this->snapshot($lockedEntry);
            $affectedDate = $lockedEntry->holiday_date?->toDateString();

            $lockedEntry->delete();

            if ($affectedDate !== null) {
                $this->worktimeEvaluationRebuildService->rebuildForAllUsers($affectedDate, $affectedDate);
            }

            $this->auditLogService->log(
                actor: $actor,
                event: 'holiday_calendar_entry.deleted',
                auditable: $entry,
                oldValues: $oldValues,
                description: 'Feiertagseintrag wurde geloescht.',
            );
        });
    }

    private function resolveDate(string $date): string
    {
        return Carbon::parse($date)->toDateString();
    }

    private function assertDateIsUnique(string $holidayDate, ?int $ignoreId): void
    {
        $exists = HolidayCalendarEntry::query()
            ->whereDate('holiday_date', $holidayDate)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
                throw ValidationException::withMessages([
                    'holiday_date' => 'Für dieses Datum existiert bereits ein Feiertagseintrag.',
                ]);
        }
    }

    private function snapshot(HolidayCalendarEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'holiday_date' => $entry->holiday_date?->toDateString(),
            'name' => $entry->name,
            'is_active' => (bool) $entry->is_active,
            'created_by' => $entry->created_by,
        ];
    }
}
