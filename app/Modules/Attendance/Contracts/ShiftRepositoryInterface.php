<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Models\Shift;
use App\Shared\Contracts\RepositoryInterface;

/**
 * @extends RepositoryInterface<Shift>
 */
interface ShiftRepositoryInterface extends RepositoryInterface
{
    public function findDefaultActive(): ?Shift;
}
