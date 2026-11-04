<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Events;

use App\Modules\CoreHR\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeOnboarded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Employee $employee,
    ) {}
}
