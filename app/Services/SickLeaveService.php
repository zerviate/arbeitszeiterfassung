<?php

namespace App\Services;

use App\Models\AbsenceRecord;
use App\Models\SickLeaveGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SickLeaveService
{
    public function __construct(
        private readonly SickLeaveConflictService $conflictService,
        private readonly DailyWorktimeEvaluationService $dailyWorktimeEvaluationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function create(
        User $employee,
        User $actor,
        Carbon|string $startDate,
        Carbon|string $endDate,
        ?string $note = null,
    ): SickLeaveGroup {
        [$startDate, $endDate] = $this->resolveRange($startDate, $endDate);
        $note = $this->normalizeNote($note);

        return DB::transaction(function () use ($employee, $actor, $startDate, $endDate, $note): SickLeaveGroup {
            User::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->conflictService->hasAnyAbsenceConflict($employee, $startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'sick_leave' => 'Im gewaehlten Zeitraum existiert bereits eine Abwesenheit.',
                ]);
            }

            if ($this->conflictService->hasConflictingVacationRequest($employee, $startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'sick_leave' => 'Im gewaehlten Zeitraum existiert bereits ein offener oder genehmigter Urlaubsantrag.',
                ]);
            }

            $sickLeaveGroup = SickLeaveGroup::query()->create([
                'group_key' => $this->generateGroupKey(),
                'user_id' => $employee->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'note' => $note,
                'recorded_by' => $actor->id,
                'meta' => [
                    'created_as_sick_leave' => true,
                ],
            ]);

            $dates = $this->conflictService->expandDates($startDate, $endDate);
            $createdRecordIds = [];

            foreach ($dates as $date) {
                $record = AbsenceRecord::query()->create([
                    'user_id' => $employee->id,
                    'type' => AbsenceRecord::TYPE_SICK_LEAVE,
                    'absence_date' => $date,
                    'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
                    'note' => $note,
                    'reference_group' => $sickLeaveGroup->group_key,
                    'sick_leave_group_id' => $sickLeaveGroup->id,
                    'absence_request_id' => null,
                    'recorded_by' => $actor->id,
                    'meta' => [
                        'created_as_sick_leave' => true,
                    ],
                ]);

                $createdRecordIds[] = $record->id;
            }

            foreach ($dates as $date) {
                $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($employee, $date);
            }

            $this->auditLogService->log(
                actor: $actor,
                event: 'sick_leave.created',
                auditable: $sickLeaveGroup,
                newValues: [
                    'group_id' => $sickLeaveGroup->id,
                    'group_key' => $sickLeaveGroup->group_key,
                    'user_id' => $employee->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => count($dates),
                    'note' => $note,
                    'record_ids' => $createdRecordIds,
                ],
                description: 'Krankmeldung wurde erfasst.',
            );

            return $sickLeaveGroup;
        }, attempts: 5);
    }

    public function updateGroup(
        SickLeaveGroup $sickLeaveGroup,
        User $actor,
        Carbon|string $startDate,
        Carbon|string $endDate,
        ?string $note = null,
    ): SickLeaveGroup {
        [$startDate, $endDate] = $this->resolveRange($startDate, $endDate);
        $note = $this->normalizeNote($note);

        return DB::transaction(function () use ($sickLeaveGroup, $actor, $startDate, $endDate, $note): SickLeaveGroup {
            $lockedGroup = SickLeaveGroup::query()
                ->whereKey($sickLeaveGroup->id)
                ->lockForUpdate()
                ->firstOrFail();

            $records = $this->findLockedGroupRecords($lockedGroup);
            $oldDates = $records
                ->map(static fn (AbsenceRecord $record): ?string => $record->absence_date?->toDateString())
                ->filter()
                ->values()
                ->all();
            $employeeId = (int) $lockedGroup->user_id;

            $employee = User::query()
                ->whereKey($employeeId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->conflictService->hasAnyAbsenceConflict($employee, $startDate, $endDate, $lockedGroup->id)) {
                throw ValidationException::withMessages([
                    'sick_leave' => 'Im gewaehlten Zeitraum existiert bereits eine andere Abwesenheit.',
                ]);
            }

            if ($this->conflictService->hasConflictingVacationRequest($employee, $startDate, $endDate)) {
                throw ValidationException::withMessages([
                    'sick_leave' => 'Im gewaehlten Zeitraum existiert bereits ein offener oder genehmigter Urlaubsantrag.',
                ]);
            }

            $oldValues = $this->groupSnapshot($lockedGroup, $records);

            AbsenceRecord::query()
                ->whereIn('id', $records->modelKeys())
                ->delete();

            $lockedGroup->forceFill([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'note' => $note,
                'recorded_by' => $actor->id,
                'meta' => array_merge($lockedGroup->meta ?? [], [
                    'updated_sick_leave' => true,
                ]),
            ])->save();

            $newRecordIds = [];
            $dates = $this->conflictService->expandDates($startDate, $endDate);

            foreach ($dates as $date) {
                $record = AbsenceRecord::query()->create([
                    'user_id' => $employee->id,
                    'type' => AbsenceRecord::TYPE_SICK_LEAVE,
                    'absence_date' => $date,
                    'source' => AbsenceRecord::SOURCE_ADMIN_RECORDED,
                    'note' => $note,
                    'reference_group' => $lockedGroup->group_key,
                    'sick_leave_group_id' => $lockedGroup->id,
                    'absence_request_id' => null,
                    'recorded_by' => $actor->id,
                    'meta' => [
                        'updated_sick_leave' => true,
                    ],
                ]);

                $newRecordIds[] = $record->id;
            }

            $affectedDates = collect(array_merge($oldDates, $dates))
                ->unique()
                ->values();

            foreach ($affectedDates as $date) {
                $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($employee, $date);
            }

            $this->auditLogService->log(
                actor: $actor,
                event: 'sick_leave.updated',
                auditable: $lockedGroup,
                oldValues: $oldValues,
                newValues: [
                    'group_id' => $lockedGroup->id,
                    'group_key' => $lockedGroup->group_key,
                    'user_id' => $employee->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => count($dates),
                    'note' => $note,
                    'record_ids' => $newRecordIds,
                ],
                description: 'Krankmeldung wurde aktualisiert.',
            );

            return $lockedGroup->refresh();
        }, attempts: 5);
    }

    public function deleteGroup(SickLeaveGroup $sickLeaveGroup, User $actor): void
    {
        DB::transaction(function () use ($sickLeaveGroup, $actor): void {
            $lockedGroup = SickLeaveGroup::query()
                ->whereKey($sickLeaveGroup->id)
                ->lockForUpdate()
                ->firstOrFail();

            $employee = User::query()
                ->whereKey($lockedGroup->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $records = $this->findLockedGroupRecords($lockedGroup);
            $oldDates = $records
                ->map(static fn (AbsenceRecord $record): ?string => $record->absence_date?->toDateString())
                ->filter()
                ->values();
            $oldValues = $this->groupSnapshot($lockedGroup, $records);

            AbsenceRecord::query()
                ->whereIn('id', $records->modelKeys())
                ->delete();

            $lockedGroup->delete();

            $this->auditLogService->log(
                actor: $actor,
                event: 'sick_leave.deleted',
                auditable: SickLeaveGroup::class,
                oldValues: $oldValues,
                newValues: [
                    'group_id' => $lockedGroup->id,
                    'group_key' => $lockedGroup->group_key,
                ],
                description: 'Krankmeldung wurde geloescht.',
            );

            foreach ($oldDates as $date) {
                $this->dailyWorktimeEvaluationService->rebuildForUserAndDate($employee, $date);
            }

        }, attempts: 5);
    }

    private function findLockedGroupRecords(SickLeaveGroup $sickLeaveGroup): EloquentCollection
    {
        $records = AbsenceRecord::query()
            ->where('type', AbsenceRecord::TYPE_SICK_LEAVE)
            ->where('sick_leave_group_id', $sickLeaveGroup->id)
            ->orderBy('absence_date')
            ->lockForUpdate()
            ->get();

        if ($records->isEmpty()) {
            throw ValidationException::withMessages([
                'sick_leave' => 'Die Krankmeldung wurde nicht gefunden.',
            ]);
        }

        return $records;
    }

    private function groupSnapshot(SickLeaveGroup $sickLeaveGroup, EloquentCollection $records): array
    {
        return [
            'group_id' => $sickLeaveGroup->id,
            'group_key' => $sickLeaveGroup->group_key,
            'user_id' => $sickLeaveGroup->user_id,
            'start_date' => $sickLeaveGroup->start_date?->toDateString(),
            'end_date' => $sickLeaveGroup->end_date?->toDateString(),
            'days' => $records->count(),
            'note' => $sickLeaveGroup->note,
            'record_ids' => $records->modelKeys(),
            'dates' => $records
                ->map(static fn (AbsenceRecord $record): ?string => $record->absence_date?->toDateString())
                ->values()
                ->all(),
        ];
    }

    private function generateGroupKey(): string
    {
        do {
            $groupKey = 'sick-'.now('UTC')->format('YmdHis').'-'.Str::lower(Str::random(10));
        } while (SickLeaveGroup::query()->where('group_key', $groupKey)->exists());

        return $groupKey;
    }

    private function resolveRange(Carbon|string $startDate, Carbon|string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'Enddatum darf nicht vor dem Startdatum liegen.',
            ]);
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $trimmed = trim($note);

        return $trimmed === '' ? null : $trimmed;
    }
}
