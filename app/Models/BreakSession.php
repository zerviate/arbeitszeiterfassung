<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakSession extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'work_session_id',
        'started_at',
        'ended_at',
        'minutes',
        'status',
        'started_by_event_id',
        'ended_by_event_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'minutes' => 'integer',
    ];

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function startedByEvent(): BelongsTo
    {
        return $this->belongsTo(TimeEvent::class, 'started_by_event_id');
    }

    public function endedByEvent(): BelongsTo
    {
        return $this->belongsTo(TimeEvent::class, 'ended_by_event_id');
    }
}
