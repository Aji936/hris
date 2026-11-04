<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\CoreHR\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 0,
            'pending_quota' => 0,
        ];
    }
}
