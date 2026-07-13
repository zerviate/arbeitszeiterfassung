<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_number',
        'timezone',
        'is_active',
        'manager_id',
        'role',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function timeEvents(): HasMany
    {
        return $this->hasMany(TimeEvent::class);
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    public function dailySummaries(): HasMany
    {
        return $this->hasMany(DailyTimeSummary::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(TimeCorrection::class);
    }

    public function correctionsRequested(): HasMany
    {
        return $this->hasMany(TimeCorrection::class, 'requested_by');
    }

    public function correctionsReviewed(): HasMany
    {
        return $this->hasMany(TimeCorrection::class, 'reviewed_by');
    }

    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }

    public function absenceRecords(): HasMany
    {
        return $this->hasMany(AbsenceRecord::class);
    }

    public function sickLeaveGroups(): HasMany
    {
        return $this->hasMany(SickLeaveGroup::class);
    }

    public function sickLeaveGroupsRecorded(): HasMany
    {
        return $this->hasMany(SickLeaveGroup::class, 'recorded_by');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function dailyWorktimeEvaluations(): HasMany
    {
        return $this->hasMany(DailyWorktimeEvaluation::class);
    }

    public function holidayCalendarEntriesCreated(): HasMany
    {
        return $this->hasMany(HolidayCalendarEntry::class, 'created_by');
    }

    public function vacationBalances(): HasMany
    {
        return $this->hasMany(VacationBalance::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array((string) $this->role, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        $explicitPermissions = $this->permissions ?? [];

        if (in_array($permission, $explicitPermissions, true)) {
            return true;
        }

        return in_array($permission, $this->roleDefaultPermissions(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isManagerOf(User $targetUser): bool
    {
        return (int) $targetUser->manager_id === (int) $this->id;
    }

    private function roleDefaultPermissions(): array
    {
        return match ((string) $this->role) {
            'employee' => [
                'time.track.own',
                'time.view.own',
                'time.correct.own',
                'time.export.own',
                'absence.request.own',
                'absence.view.own',
                'absence.cancel.own',
            ],
            'manager' => [
                'time.track.own',
                'time.view.own',
                'time.view.team',
                'time.correct.own',
                'time.correct.request.for_others',
                'time.correct.review',
                'time.finalize.team',
                'time.export.own',
                'time.export.team',
                'absence.request.own',
                'absence.view.own',
                'absence.cancel.own',
                'absence.view.team',
                'absence.request.for_others',
                'absence.review.team',
            ],
            'hr' => [
                'time.view.all',
                'time.correct.review',
                'time.finalize.all',
                'time.audit.view',
                'time.export.all',
                'time.contract.manage',
                'time.holiday.manage',
                'absence.view.all',
                'absence.review.all',
                'absence.request.for_others',
                'absence.sick.manage',
                'absence.vacation.balance.manage',
            ],
            'auditor' => [
                'time.audit.view',
                'time.view.all',
                'absence.view.all',
            ],
            default => [],
        };
    }
}
