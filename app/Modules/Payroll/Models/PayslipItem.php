<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Enums\PayrollStatus;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\PayslipItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayslipItem extends Model
{
    /** @use HasFactory<PayslipItemFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'payslip_items';

    protected $fillable = [
        'payslip_id',
        'payroll_component_id',
        'name',
        'type',
        'amount',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayrollComponentType::class,
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $item): void {
            self::assertPayslipMutable($item->payslip_id);
        });

        static::updating(static function (self $item): void {
            $payslipId = $item->getOriginal('payslip_id') ?? $item->payslip_id;
            self::assertPayslipMutable($payslipId);
        });

        static::deleting(static function (self $item): void {
            $payslipId = $item->getOriginal('payslip_id') ?? $item->payslip_id;
            self::assertPayslipMutable($payslipId);
        });
    }

    private static function assertPayslipMutable(int|string|null $payslipId): void
    {
        if ($payslipId === null) {
            return;
        }

        $payrollId = Payslip::withTrashed()->whereKey($payslipId)->value('payroll_id');
        if ($payrollId === null) {
            return;
        }

        $rawStatus = Payroll::withTrashed()->whereKey($payrollId)->value('status');
        $statusEnum = $rawStatus instanceof PayrollStatus
            ? $rawStatus
            : PayrollStatus::tryFrom((string) $rawStatus);

        if ($statusEnum !== null && $statusEnum->isImmutable()) {
            throw new DomainException('Payslip items are immutable once the payroll batch is approved, published, or paid.');
        }
    }

    protected static function newFactory(): PayslipItemFactory
    {
        return PayslipItemFactory::new();
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'payslip_id');
    }

    public function payrollComponent(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
