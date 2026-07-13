<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceRecord extends Model
{
    use HasFactory;

    public const TYPE_VACATION = 'vacation';
    public const TYPE_SICK_LEAVE = 'sick_leave';

    public const SOURCE_REQUEST_APPROVED = 'request_approved';
    public const SOURCE_ADMIN_RECORDED = 'admin_recorded';

    protected $fillable = [
        'user_id',
        'type',
        'absence_date',
        'source',
        'note',
        'reference_group',
        'sick_leave_group_id',
        'absence_request_id',
        'recorded_by',
        'meta',
    ];

    protected $casts = [
        'absence_date' => 'date',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function absenceRequest(): BelongsTo
    {
        return $this->belongsTo(AbsenceRequest::class, 'absence_request_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function sickLeaveGroup(): BelongsTo
    {
        return $this->belongsTo(SickLeaveGroup::class);
    }
}
