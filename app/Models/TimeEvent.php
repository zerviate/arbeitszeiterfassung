<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeEvent extends Model
{
    use HasFactory;

    public const TYPE_CLOCK_IN = 'clock_in';
    public const TYPE_CLOCK_OUT = 'clock_out';
    public const TYPE_BREAK_START = 'break_start';
    public const TYPE_BREAK_END = 'break_end';
    public const TYPE_MANUAL_ENTRY = 'manual_entry';
    public const TYPE_MANUAL_CORRECTION = 'manual_correction';

    public const SOURCE_WEB = 'web';
    public const SOURCE_MOBILE = 'mobile';
    public const SOURCE_TERMINAL = 'terminal';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'user_id',
        'type',
        'occurred_at',
        'work_date',
        'source',
        'created_by',
        'reason',
        'meta',
        'invalidated_at',
        'invalidated_by',
        'invalidation_reason',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'work_date' => 'date',
        'meta' => 'array',
        'invalidated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }

    public function openedWorkSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class, 'opened_by_event_id');
    }

    public function closedWorkSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class, 'closed_by_event_id');
    }

    public function startedBreaks(): HasMany
    {
        return $this->hasMany(BreakSession::class, 'started_by_event_id');
    }

    public function endedBreaks(): HasMany
    {
        return $this->hasMany(BreakSession::class, 'ended_by_event_id');
    }

    public function resolvedType(): string
    {
        if ($this->type !== self::TYPE_MANUAL_CORRECTION) {
            return $this->type;
        }

        $correctedType = $this->meta['corrected_type'] ?? null;

        return is_string($correctedType) ? $correctedType : $this->type;
    }
}
