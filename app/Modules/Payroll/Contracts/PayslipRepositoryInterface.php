<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Contracts;

use App\Modules\Payroll\Models\Payslip;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<Payslip>
 */
interface PayslipRepositoryInterface extends RepositoryInterface
{
    public function findWithDetails(int|string $id): ?Payslip;

    public function publishAllForPayroll(int|string $payrollId): int;

    public function purgeDraftsForPayroll(int|string $payrollId): void;

    /**
     * @return LengthAwarePaginator<Payslip>
     */
    public function paginatePublishedForEmployee(int|string $employeeId, int $perPage = 15): LengthAwarePaginator;
}
