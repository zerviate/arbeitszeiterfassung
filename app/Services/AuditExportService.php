<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\SpreadsheetValueSanitizer;
use Illuminate\Database\Eloquent\Collection;

class AuditExportService
{
    public function getExportRows(array $filters = []): array
    {
        $logs = $this->resolveLogs($filters);

        return $logs->map(function (AuditLog $log): array {
            return SpreadsheetValueSanitizer::sanitizeRow([
                'Zeitpunkt' => $log->created_at?->toDateTimeString(),
                'Actor' => $log->actor?->name,
                'Actor_ID' => $log->actor_id,
                'Event' => $log->event,
                'Objekt_Typ' => $log->auditable_type,
                'Objekt_ID' => $log->auditable_id,
                'Beschreibung' => $log->description,
                'IP_Adresse' => $log->ip_address,
                'User_Agent' => $log->user_agent,
                'Vorher' => $this->encode($log->old_values),
                'Nachher' => $this->encode($log->new_values),
                'Meta' => $this->encode($log->meta),
            ]);
        })->values()->all();
    }

    private function resolveLogs(array $filters): Collection
    {
        $query = AuditLog::query()->with('actor');

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    private function encode(mixed $payload): string
    {
        if ($payload === null) {
            return '';
        }

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
