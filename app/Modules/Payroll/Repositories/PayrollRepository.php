<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Repositories;

use App\Modules\Payroll\Contracts\PayrollRepositoryInterface;
use App\Modules\Payroll\Models\Payroll;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Payroll>
 */
class PayrollRepository extends BaseRepository implements PayrollRepositoryInterface
{
    protected function model(): string
    {
        return Payroll::class;
    }

    public function findByPeriod(string $period, bool $lockForUpdate = false): ?Payroll
    {
        $query = $this->query()->where('payroll_period', trim($period));

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Payroll|null $payroll */
        $payroll = $query->first();

        return $payroll;
    }

    public function findWithPayslips(int|string $id, bool $lockForUpdate = false): ?Payroll
    {
        $query = $this->query()
            ->with([
                'generator:id,name,email',
                'approver:id,name,email',
                'payslips.items',
            ])
            ->whereKey($id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Payroll|null $payroll */
        $payroll = $query->first();

        return $payroll;
    }

    public function paginateBatches(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->withCount('payslips')
            ->with([
                'generator:id,name,email',
                'approver:id,name,email',
            ])
            ->when(
                ! empty($filters['status']),
                static fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->orderByDesc('payroll_period')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
