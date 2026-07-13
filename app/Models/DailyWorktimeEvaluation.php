<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyWorktimeEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'contract_id',
        'is_scheduled_workday',
        'is_holiday',
        'holiday_name',
        'target_minutes',
        'actual_minutes',
        'vacation_minutes',
        'sick_leave_minutes',
        'balance_minutes',
        'day_status',
        'traffic_light',
        'flags',
    ];

    protected $casts = [
        'work_date' => 'date',
        'is_scheduled_workday' => 'boolean',
        'is_holiday' => 'boolean',
        'target_minutes' => 'integer',
        'actual_minutes' => 'integer',
        'vacation_minutes' => 'integer',
        'sick_leave_minutes' => 'integer',
        'balance_minutes' => 'integer',
        'flags' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
