<?php

namespace App\Services;

use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Support\SpreadsheetValueSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ComplianceExportService
{
    public function __construct(
        private readonly WorktimeEvaluationRebuildService $worktimeEvaluationRebuildService,
    ) {
    }

    public function getDayExportRows(User $actor, string $date): array
    {
        $date = $this->resolveDate($date);
        $users = $this->resolveVisibleUsers($actor);

        $this->worktimeEvaluationRebuildService->rebuildForUsersAndRange($users, $date, $date);

        $evaluations = DailyWorktimeEvaluation::query()
            ->with('user')
            ->whereIn('user_id', $users->modelKeys())
            ->whereDate('work_date', $date)
            ->orderBy('user_id')
            ->get();

        return $this->mapRows($evaluations);
    }

    public function getWeekExportRows(User $actor, string $date): array
    {
        $date = Carbon::parse($this->resolveDate($date));
        $from = $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $to = $date->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $users = $this->resolveVisibleUsers($actor);

        $this->worktimeEvaluationRebuildService->rebuildForUsersAndRange($users, $from, $to);

        $evaluations = DailyWorktimeEvaluation::query()
            ->with('user')
            ->whereIn('user_id', $users->modelKeys())
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();

        return $this->mapRows($evaluations);
    }

    public function getMonthExportRows(User $actor, string $month): array
    {
        [$from, $to] = $this->resolveMonthRange($month);
        $users = $this->resolveVisibleUsers($actor);

        $this->worktimeEvaluationRebuildService->rebuildForUsersAndRange($users, $from, $to);

        $evaluations = DailyWorktimeEvaluation::query()
            ->with('user')
            ->whereIn('user_id', $users->modelKeys())
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();

        return $this->mapRows($evaluations);
    }

    private function mapRows(Collection $evaluations): array
    {
        return $evaluations->map(function (DailyWorktimeEvaluation $evaluation): array {
            return SpreadsheetValueSanitizer::sanitizeRow([
                'Datum' => $evaluation->work_date?->toDateString(),
                'Mitarbeiter' => $evaluation->user?->name,
                'Personalnummer' => $evaluation->user?->employee_number,
                'Soll_Minuten' => $evaluation->target_minutes,
                'Ist_Minuten' => $evaluation->actual_minutes,
                'Urlaub_Minuten' => $evaluation->vacation_minutes,
                'Krank_Minuten' => $evaluation->sick_leave_minutes,
                'Saldo_Minuten' => $evaluation->balance_minutes,
                'Tagesstatus' => $evaluation->day_status,
                'Ampel' => $evaluation->traffic_light,
                'Feiertag' => $evaluation->is_holiday ? ($evaluation->holiday_name ?: 'Ja') : 'Nein',
                'Flags' => implode(', ', $evaluation->flags ?? []),
            ]);
        })->values()->all();
    }

    private function resolveVisibleUsers(User $actor): Collection
    {
        $query = User::query()->orderBy('name');

        if ($actor->hasPermission('time.export.all')) {
            return $query->get();
        }

        if ($actor->hasPermission('time.export.team')) {
            return $query
                ->where(function ($innerQuery) use ($actor): void {
                    $innerQuery
                        ->where('manager_id', $actor->id)
                        ->orWhere('id', $actor->id);
                })
                ->get();
        }

        return $query->whereKey($actor->id)->get();
    }

    private function resolveDate(string $date): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw ValidationException::withMessages([
                'date' => 'Datum muss im Format YYYY-MM-DD angegeben werden.',
            ]);
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $date, 'UTC');

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages([
                'date' => 'Datum ist ungueltig.',
            ]);
        }

        return $parsed->toDateString();
    }

    private function resolveMonthRange(string $month): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages([
                'month' => 'Monat muss im Format YYYY-MM angegeben werden.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m', $month, 'UTC');

        if ($start === false || $start->format('Y-m') !== $month) {
            throw ValidationException::withMessages([
                'month' => 'Monat ist ungueltig.',
            ]);
        }

        return [
            $start->copy()->startOfMonth()->toDateString(),
            $start->copy()->endOfMonth()->toDateString(),
        ];
    }
}
