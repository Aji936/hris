<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\Shift;
use App\Modules\CoreHR\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = $this->faker->date('Y-m-d');

        return [
            'employee_id' => Employee::factory(),
            'shift_id' => Shift::factory(),
            'date' => $date,
            'clock_in' => $date.' 08:55:00',
            'clock_out' => $date.' 17:05:00',
            'clock_in_ip' => '127.0.0.1',
            'clock_out_ip' => '127.0.0.1',
            'clock_in_location' => '-6.200000,106.816666',
            'clock_out_location' => '-6.200000,106.816666',
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'work_duration_minutes' => 490,
            'status' => AttendanceStatus::PRESENT,
            'notes' => null,
        ];
    }
}
