<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use App\Models\User;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\Shift;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Enums\MaritalStatus;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\Payslip;
use App\Shared\Traits\HasAuditColumns;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    public const RETIREMENT_AGE_YEARS = 55;

    protected $table = 'employees';

    protected $fillable = [
        'user_id',
        'employee_code',
        'full_name',
        'date_of_birth',
        'retirement_date',
        'phone_number',
        'address',
        'marital_status',
        'dependents_count',
        'department_id',
        'position_id',
        'shift_id',
        'manager_id',
        'join_date',
        'status',
        'salary_grade',
        'basic_salary',
        'national_id_number',
        'tax_id_number',
        'bank_account_number',
        'bank_name',
        'created_by',
        'updated_by',
    ];

    /**
     * Prevent accidental exposure of sensitive PII and financial fields in default serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'national_id_number',
        'tax_id_number',
        'bank_account_number',
        'basic_salary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'retirement_date' => 'date',
            'join_date' => 'date',
            'status' => EmploymentStatus::class,
            'marital_status' => MaritalStatus::class,
            'dependents_count' => 'integer',
            'basic_salary' => 'decimal:2',
            'national_id_number' => 'encrypted',
            'tax_id_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $employee): void {
            if ($employee->date_of_birth === null) {
                return;
            }

            $shouldAutoCalculate = $employee->retirement_date === null
                || ($employee->isDirty('date_of_birth') && ! $employee->isDirty('retirement_date'));

            if ($shouldAutoCalculate) {
                $dob = CarbonImmutable::parse($employee->date_of_birth)->startOfDay();
                $employee->retirement_date = $dob->addYears(self::RETIREMENT_AGE_YEARS)->toDateString();
            }
        });
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    public function getAgeAttribute(): ?int
    {
        if ($this->date_of_birth === null) {
            return null;
        }

        return (int) CarbonImmutable::instance($this->date_of_birth)->age;
    }

    public function getDaysUntilRetirementAttribute(): ?int
    {
        if ($this->retirement_date === null) {
            return null;
        }

        $today = CarbonImmutable::now()->startOfDay();
        $retirement = CarbonImmutable::instance($this->retirement_date)->startOfDay();

        return (int) $today->diffInDays($retirement, false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'employee_id');
    }

    public function activeSalary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class, 'employee_id')
            ->where('is_active', true)
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'employee_id');
    }
}
