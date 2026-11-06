<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Enums\PayrollStatus;
use App\Modules\Payroll\Enums\PayslipStatus;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'payslips';

    protected $fillable = [
        'payroll_id',
        'employee_id',
        'payslip_number',
        'employee_snapshot',
        'basic_salary',
        'total_allowance',
        'total_deduction',
        'net_salary',
        'attendance_summary',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_snapshot' => 'array',
            'basic_salary' => 'decimal:2',
            'total_allowance' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'attendance_summary' => 'array',
            'status' => PayslipStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $payslip): void {
            self::assertPayrollMutable($payslip->payroll_id);
        });

        static::updating(static function (self $payslip): void {
            $payrollId = $payslip->getOriginal('payroll_id') ?? $payslip->payroll_id;
            self::assertPayrollMutable($payrollId);

            $originalPayslipStatus = $payslip->getOriginal('status');
            $payslipStatusEnum = $originalPayslipStatus instanceof PayslipStatus
                ? $originalPayslipStatus
                : PayslipStatus::tryFrom((string) $originalPayslipStatus);

            if ($payslipStatusEnum !== null && $payslipStatusEnum->isEmployeeVisible()) {
                $immutablePayslipFields = [
                    'payroll_id',
                    'employee_id',
                    'payslip_number',
                    'employee_snapshot',
                    'basic_salary',
                    'total_allowance',
                    'total_deduction',
                    'net_salary',
                    'attendance_summary',
                ];

                if ($payslip->isDirty($immutablePayslipFields)) {
                    throw new DomainException('Published or paid payslips are immutable.');
                }

                if ($payslip->isDirty('status')) {
                    $nextStatus = $payslip->status instanceof PayslipStatus
                        ? $payslip->status
                        : PayslipStatus::tryFrom((string) $payslip->status);

                    if ($nextStatus === PayslipStatus::DRAFT || ($payslipStatusEnum === PayslipStatus::PAID && $nextStatus !== PayslipStatus::PAID)) {
                        throw new DomainException('Cannot revert a published or paid payslip.');
                    }
                }
            }
        });

        $guardDelete = static function (self $payslip): void {
            $payrollId = $payslip->getOriginal('payroll_id') ?? $payslip->payroll_id;
            self::assertPayrollMutable($payrollId);

            $originalPayslipStatus = $payslip->getOriginal('status') ?? $payslip->status;
            $payslipStatusEnum = $originalPayslipStatus instanceof PayslipStatus
                ? $originalPayslipStatus
                : PayslipStatus::tryFrom((string) $originalPayslipStatus);

            if ($payslipStatusEnum !== null && $payslipStatusEnum->isEmployeeVisible()) {
                throw new DomainException('Cannot delete a published or paid payslip.');
            }
        };

        static::deleting($guardDelete);
        static::forceDeleting($guardDelete);
    }

    private static function assertPayrollMutable(int|string|null $payrollId): void
    {
        if ($payrollId === null) {
            return;
        }

        $rawStatus = Payroll::withTrashed()->whereKey($payrollId)->value('status');
        $statusEnum = $rawStatus instanceof PayrollStatus
            ? $rawStatus
            : PayrollStatus::tryFrom((string) $rawStatus);

        if ($statusEnum !== null && $statusEnum->isImmutable()) {
            throw new DomainException('Payslip is immutable once its payroll batch is approved, published, or paid.');
        }
    }

    protected static function newFactory(): PayslipFactory
    {
        return PayslipFactory::new();
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class, 'payslip_id');
    }
}
