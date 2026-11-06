<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Repositories;

use App\Modules\Payroll\Contracts\PayslipRepositoryInterface;
use App\Modules\Payroll\Enums\PayslipStatus;
use App\Modules\Payroll\Models\Payslip;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<Payslip>
 */
class PayslipRepository extends BaseRepository implements PayslipRepositoryInterface
{
    protected function model(): string
    {
        return Payslip::class;
    }

    public function findWithDetails(int|string $id): ?Payslip
    {
        /** @var Payslip|null $payslip */
        $payslip = $this->query()
            ->with([
                'payroll',
                'employee',
                'items',
            ])
            ->whereKey($id)
            ->first();

        return $payslip;
    }

    public function publishAllForPayroll(int|string $payrollId): int
    {
        return $this->query()
            ->where('payroll_id', $payrollId)
            ->update([
                'status' => PayslipStatus::PUBLISHED->value,
            ]);
    }

    public function purgeDraftsForPayroll(int|string $payrollId): void
    {
        $payslips = $this->query()
            ->withTrashed()
            ->where('payroll_id', $payrollId)
            ->get();

        foreach ($payslips as $payslip) {
            $payslip->items()->withTrashed()->forceDelete();
            $payslip->forceDelete();
        }
    }

    public function paginatePublishedForEmployee(int|string $employeeId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['payroll', 'items'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', [
                PayslipStatus::PUBLISHED->value,
                PayslipStatus::PAID->value,
            ])
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
