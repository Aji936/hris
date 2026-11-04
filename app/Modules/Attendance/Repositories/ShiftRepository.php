<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Modules\Attendance\Contracts\ShiftRepositoryInterface;
use App\Modules\Attendance\Models\Shift;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<Shift>
 */
class ShiftRepository extends BaseRepository implements ShiftRepositoryInterface
{
    protected function model(): string
    {
        return Shift::class;
    }

    public function findDefaultActive(): ?Shift
    {
        /** @var Shift|null $shift */
        $shift = $this->query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return $shift;
    }
}
