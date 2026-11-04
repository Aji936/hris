<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Repositories;

use App\Modules\CoreHR\Contracts\PositionRepositoryInterface;
use App\Modules\CoreHR\Models\Position;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Position>
 */
class PositionRepository extends BaseRepository implements PositionRepositoryInterface
{
    protected function model(): string
    {
        return Position::class;
    }

    public function findByDepartment(int|string $departmentId): Collection
    {
        return $this->query()
            ->where('department_id', $departmentId)
            ->orderBy('title')
            ->get();
    }
}
