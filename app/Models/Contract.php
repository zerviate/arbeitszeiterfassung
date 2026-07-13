<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    public const DAY_KEYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    protected $fillable = [
        'user_id',
        'weekly_minutes',
        'workdays_pattern',
        'valid_from',
        'valid_to',
        'is_active',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'weekly_minutes' => 'integer',
        'workdays_pattern' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dailyWorktimeEvaluations(): HasMany
    {
        return $this->hasMany(DailyWorktimeEvaluation::class);
    }
}
