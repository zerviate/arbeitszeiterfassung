<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'remember_token',
        'token',
        'secret',
        'api_token',
    ];

    public function log(
        ?User $actor,
        string $event,
        Model|string|null $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $meta = [],
        ?string $description = null,
    ): AuditLog {
        [$auditableType, $auditableId] = $this->resolveAuditable($auditable);

        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'description' => $description,
            'ip_address' => $this->resolveIpAddress(),
            'user_agent' => $this->resolveUserAgent(),
            'old_values' => $this->sanitizePayload($oldValues),
            'new_values' => $this->sanitizePayload($newValues),
            'meta' => $this->sanitizePayload($meta),
        ]);
    }

    public function logModelCreated(?User $actor, Model $model, array $meta = []): AuditLog
    {
        return $this->log(
            actor: $actor,
            event: strtolower(class_basename($model)).'.created',
            auditable: $model,
            oldValues: [],
            newValues: $model->getAttributes(),
            meta: $meta,
            description: class_basename($model).' wurde erstellt.',
        );
    }

    public function logModelUpdated(?User $actor, Model $model, array $oldValues, array $newValues, array $meta = []): AuditLog
    {
        return $this->log(
            actor: $actor,
            event: strtolower(class_basename($model)).'.updated',
            auditable: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            meta: $meta,
            description: class_basename($model).' wurde aktualisiert.',
        );
    }

    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '***';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function resolveAuditable(Model|string|null $auditable): array
    {
        if ($auditable instanceof Model) {
            return [
                $auditable::class,
                (int) $auditable->getKey(),
            ];
        }

        if (is_string($auditable)) {
            return [$auditable, null];
        }

        return [null, null];
    }

    private function resolveIpAddress(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request()->ip();
    }

    private function resolveUserAgent(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request()->userAgent();
    }
}
