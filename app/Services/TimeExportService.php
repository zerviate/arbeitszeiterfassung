<?php

namespace App\Services;

use App\Models\DailyTimeSummary;
use App\Models\DailyWorktimeEvaluation;
use App\Models\User;
use App\Support\SpreadsheetValueSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TimeExportService
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

        $evaluationsByKey = DailyWorktimeEvaluation::query()
            ->whereIn('user_id', $users->modelKeys())
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy(fn (DailyWorktimeEvaluation $item): string => $item->user_id.'#'.$item->work_date?->toDateString());

        $summaries = DailyTimeSummary::query()
            ->with('user')
            ->whereIn('user_id', $users->modelKeys())
            ->where('work_date', $date)
            ->orderBy('user_id')
            ->get();

        return $summaries->map(function (DailyTimeSummary $summary) use ($evaluationsByKey): array {
            $workDate = $summary->work_date?->toDateString();
            $evaluationKey = $summary->user_id.'#'.$workDate;
            $evaluation = $evaluationsByKey->get($evaluationKey);

            return SpreadsheetValueSanitizer::sanitizeRow([
                'Datum' => $workDate,
                'Mitarbeiter' => $summary->user?->name,
                'Personalnummer' => $summary->user?->employee_number,
                'Brutto_Minuten' => $summary->gross_minutes,
                'Pause_Minuten' => $summary->break_minutes,
                'Netto_Minuten' => $summary->net_minutes,
                'Ueberzeit_Minuten' => $summary->overtime_minutes,
                'Soll_Minuten' => $evaluation?->target_minutes,
                'Saldo_Minuten' => $evaluation?->balance_minutes,
                'Tagesstatus' => $evaluation?->day_status,
                'Ampel' => $evaluation?->traffic_light,
                'Feiertag' => $evaluation?->is_holiday ? ($evaluation->holiday_name ?: 'Ja') : 'Nein',
                'Offene_Eintraege' => $summary->has_open_entries ? 'Ja' : 'Nein',
                'Manuelle_Korrekturen' => $summary->has_manual_corrections ? 'Ja' : 'Nein',
                'Finalisiert' => $summary->finalized_at ? 'Ja' : 'Nein',
                'Verstoesse' => implode(', ', $summary->violation_flags ?? []),
                'Bewertungs_Flags' => implode(', ', $evaluation?->flags ?? []),
            ]);
        })->values()->all();
    }

    public function getMonthExportRows(User $actor, string $month): array
    {
        [$from, $to] = $this->resolveMonthRange($month);
        $users = $this->resolveVisibleUsers($actor);

        $this->worktimeEvaluationRebuildService->rebuildForUsersAndRange($users, $from, $to);

        $evaluationsByKey = DailyWorktimeEvaluation::query()
            ->whereIn('user_id', $users->modelKeys())
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->keyBy(fn (DailyWorktimeEvaluation $item): string => $item->user_id.'#'.$item->work_date?->toDateString());

        $summaries = DailyTimeSummary::query()
            ->with('user')
            ->whereIn('user_id', $users->modelKeys())
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();

        return $summaries->map(function (DailyTimeSummary $summary) use ($evaluationsByKey): array {
            $workDate = $summary->work_date?->toDateString();
            $evaluationKey = $summary->user_id.'#'.$workDate;
            $evaluation = $evaluationsByKey->get($evaluationKey);

            return SpreadsheetValueSanitizer::sanitizeRow([
                'Datum' => $workDate,
                'Mitarbeiter' => $summary->user?->name,
                'Personalnummer' => $summary->user?->employee_number,
                'Brutto_Stunden' => round($summary->gross_minutes / 60, 2),
                'Pause_Stunden' => round($summary->break_minutes / 60, 2),
                'Netto_Stunden' => round($summary->net_minutes / 60, 2),
                'Ueberzeit_Stunden' => round($summary->overtime_minutes / 60, 2),
                'Soll_Stunden' => $evaluation ? round($evaluation->target_minutes / 60, 2) : null,
                'Saldo_Stunden' => $evaluation ? round($evaluation->balance_minutes / 60, 2) : null,
                'Tagesstatus' => $evaluation?->day_status,
                'Ampel' => $evaluation?->traffic_light,
                'Feiertag' => $evaluation?->is_holiday ? ($evaluation->holiday_name ?: 'Ja') : 'Nein',
                'Offene_Eintraege' => $summary->has_open_entries ? 'Ja' : 'Nein',
                'Manuelle_Korrekturen' => $summary->has_manual_corrections ? 'Ja' : 'Nein',
                'Finalisiert' => $summary->finalized_at ? 'Ja' : 'Nein',
                'Verstoesse' => implode(', ', $summary->violation_flags ?? []),
                'Bewertungs_Flags' => implode(', ', $evaluation?->flags ?? []),
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
