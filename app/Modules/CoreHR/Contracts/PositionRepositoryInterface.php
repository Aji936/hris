<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Contracts;

use App\Modules\CoreHR\Models\Position;
use App\Shared\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Position>
 */
interface PositionRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, Position>
     */
    public function findByDepartment(int|string $departmentId): Collection;
}
