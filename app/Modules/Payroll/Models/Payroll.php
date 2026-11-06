<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Payroll\Enums\PayrollStatus;
use App\Shared\Exceptions\DomainException;
use App\Shared\Traits\HasAuditColumns;
use Database\Factories\PayrollFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    /** @use HasFactory<PayrollFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    protected $table = 'payrolls';

    protected $fillable = [
        'payroll_period',
        'start_date',
        'end_date',
        'title',
        'status',
        'total_gross_amount',
        'total_deduction_amount',
        'total_net_amount',
        'generated_by',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PayrollStatus::class,
            'total_gross_amount' => 'decimal:2',
            'total_deduction_amount' => 'decimal:2',
            'total_net_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $payroll): void {
            $originalStatus = $payroll->getOriginal('status');
            $statusEnum = $originalStatus instanceof PayrollStatus
                ? $originalStatus
                : PayrollStatus::tryFrom((string) $originalStatus);

            if ($statusEnum === null || ! $statusEnum->isImmutable()) {
                return;
            }

            $protectedFields = [
                'payroll_period',
                'start_date',
                'end_date',
                'title',
                'total_gross_amount',
                'total_deduction_amount',
                'total_net_amount',
                'generated_by',
                'approved_by',
                'approved_at',
            ];

            if ($payroll->isDirty($protectedFields)) {
                throw new DomainException('Approved, published, or paid payroll batches are immutable.');
            }

            if ($payroll->isDirty('status')) {
                $targetStatus = $payroll->status instanceof PayrollStatus
                    ? $payroll->status
                    : PayrollStatus::tryFrom((string) $payroll->status);

                if ($targetStatus === null || ! $targetStatus->isImmutable()) {
                    throw new DomainException('Cannot revert an approved, published, or paid payroll batch.');
                }

                $progressionRank = [
                    PayrollStatus::APPROVED->value => 1,
                    PayrollStatus::PUBLISHED->value => 2,
                    PayrollStatus::PAID->value => 3,
                ];

                if (($progressionRank[$targetStatus->value] ?? 0) < ($progressionRank[$statusEnum->value] ?? 0)) {
                    throw new DomainException('Cannot move an immutable payroll batch backward in status.');
                }
            }
        });

        $guardDelete = static function (self $payroll): void {
            $originalStatus = $payroll->getOriginal('status') ?? $payroll->status;
            $statusEnum = $originalStatus instanceof PayrollStatus
                ? $originalStatus
                : PayrollStatus::tryFrom((string) $originalStatus);

            if ($statusEnum !== null && $statusEnum->isImmutable()) {
                throw new DomainException('Cannot delete an approved, published, or paid payroll batch.');
            }
        };

        static::deleting($guardDelete);
        static::forceDeleting($guardDelete);
    }

    protected static function newFactory(): PayrollFactory
    {
        return PayrollFactory::new();
    }

    public function isLocked(): bool
    {
        return $this->status->isImmutable();
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'payroll_id');
    }
}
