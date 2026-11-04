<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\CoreHR\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'total_days' => 3,
            'reason' => $this->faker->sentence(8),
            'attachment_path' => null,
            'status' => LeaveRequestStatus::PENDING,
            'approver_id' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ];
    }
}
