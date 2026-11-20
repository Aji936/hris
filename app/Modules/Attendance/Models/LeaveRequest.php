<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Models\User;
use App\Modules\Attendance\Enums\LeavePermitType;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\CoreHR\Models\Employee;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'application_date',
        'applicant_employee_code',
        'applicant_hire_date',
        'application_type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'total_days',
        'reason',
        'attachment_path',
        'previous_leave_balance',
        'current_leave_taken',
        'remaining_leave_balance',
        'status',
        'is_rejected',
        'approver_id',
        'approved_at',
        'rejection_reason',
        'hrd_other_notes',
        'applicant_signed_at',
        'approved_1_by',
        'approved_1_at',
        'approved_2_by',
        'approved_2_at',
        'approved_3_by',
        'approved_3_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'applicant_hire_date' => 'date',
            'application_type' => LeavePermitType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => LeaveRequestStatus::class,
            'is_rejected' => 'boolean',
            'approved_at' => 'datetime',
            'applicant_signed_at' => 'datetime',
            'approved_1_at' => 'datetime',
            'approved_2_at' => 'datetime',
            'approved_3_at' => 'datetime',
        ];
    }

    public static function normalizeDays(float|int|string|null $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $floatVal = round((float) $value, 1);
        $intVal = (int) $floatVal;

        return abs($floatVal - $intVal) < 0.001 ? $intVal : $floatVal;
    }

    public function getTotalDaysAttribute(mixed $value): int|float
    {
        return self::normalizeDays($value) ?? 0;
    }

    public function getPreviousLeaveBalanceAttribute(mixed $value): int|float|null
    {
        return self::normalizeDays($value);
    }

    public function getCurrentLeaveTakenAttribute(mixed $value): int|float|null
    {
        return self::normalizeDays($value);
    }

    public function getRemainingLeaveBalanceAttribute(mixed $value): int|float|null
    {
        return self::normalizeDays($value);
    }

    protected static function newFactory(): LeaveRequestFactory
    {
        return LeaveRequestFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function approver1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_1_by');
    }

    public function approver2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_2_by');
    }

    public function approver3(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_3_by');
    }
}
