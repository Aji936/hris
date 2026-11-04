<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Repositories;

use App\Modules\Payroll\Contracts\PayrollComponentRepositoryInterface;
use App\Modules\Payroll\Models\PayrollComponent;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<PayrollComponent>
 */
class PayrollComponentRepository extends BaseRepository implements PayrollComponentRepositoryInterface
{
    protected function model(): string
    {
        return PayrollComponent::class;
    }

    public function findByCode(string $code): ?PayrollComponent
    {
        /** @var PayrollComponent|null $component */
        $component = $this->query()
            ->where('code', strtoupper(trim($code)))
            ->first();

        return $component;
    }

    public function paginateComponents(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->when(
                ! empty($filters['type']),
                static fn (Builder $query) => $query->where('type', $filters['type']),
            )
            ->when(
                isset($filters['is_active']),
                static fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']),
            )
            ->orderBy('code')
            ->paginate($perPage);
    }
}
