<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayCalendarEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'name',
        'is_active',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
