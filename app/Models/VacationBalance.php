<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'annual_entitlement_days',
        'carryover_days',
        'manual_adjustment_days',
        'note',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'year' => 'integer',
        'annual_entitlement_days' => 'decimal:2',
        'carryover_days' => 'decimal:2',
        'manual_adjustment_days' => 'decimal:2',
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
}
