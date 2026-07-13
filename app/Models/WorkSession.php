<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSession extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CORRECTED = 'corrected';
    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'user_id',
        'work_date',
        'started_at',
        'ended_at',
        'gross_minutes',
        'status',
        'opened_by_event_id',
        'closed_by_event_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'work_date' => 'date',
        'gross_minutes' => 'integer',
    ];

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function openedByEvent(): BelongsTo
    {
        return $this->belongsTo(TimeEvent::class, 'opened_by_event_id');
    }

    public function closedByEvent(): BelongsTo
    {
        return $this->belongsTo(TimeEvent::class, 'closed_by_event_id');
    }
}
