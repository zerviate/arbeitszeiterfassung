<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTimeSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'gross_minutes',
        'break_minutes',
        'net_minutes',
        'overtime_minutes',
        'has_open_entries',
        'has_manual_corrections',
        'violation_flags',
        'finalized_at',
        'finalized_by',
    ];

    protected $casts = [
        'work_date' => 'date',
        'gross_minutes' => 'integer',
        'break_minutes' => 'integer',
        'net_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'has_open_entries' => 'boolean',
        'has_manual_corrections' => 'boolean',
        'violation_flags' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
