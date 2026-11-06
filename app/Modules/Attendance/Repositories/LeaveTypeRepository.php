<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Modules\Attendance\Contracts\LeaveTypeRepositoryInterface;
use App\Modules\Attendance\Models\LeaveType;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<LeaveType>
 */
class LeaveTypeRepository extends BaseRepository implements LeaveTypeRepositoryInterface
{
    protected function model(): string
    {
        return LeaveType::class;
    }

    public function findByCode(string $code): ?LeaveType
    {
        /** @var LeaveType|null $leaveType */
        $leaveType = $this->query()
            ->where('code', strtoupper($code))
            ->first();

        return $leaveType;
    }

    public function getActiveTypes(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
