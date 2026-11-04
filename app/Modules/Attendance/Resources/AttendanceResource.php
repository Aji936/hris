<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Resources;

use App\Modules\Attendance\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'shift_id' => $this->shift_id,
            'date' => $this->date?->toDateString(),
            'clock_in' => $this->clock_in?->toIso8601String(),
            'clock_out' => $this->clock_out?->toIso8601String(),
            'clock_in_ip' => $this->clock_in_ip,
            'clock_out_ip' => $this->clock_out_ip,
            'clock_in_location' => $this->clock_in_location,
            'clock_out_location' => $this->clock_out_location,
            'late_minutes' => $this->late_minutes,
            'early_leave_minutes' => $this->early_leave_minutes,
            'work_duration_minutes' => $this->work_duration_minutes,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee?->id,
                'employee_code' => $this->employee?->employee_code,
                'full_name' => $this->employee?->full_name,
            ]),
            'shift' => $this->whenLoaded('shift', fn () => [
                'id' => $this->shift?->id,
                'name' => $this->shift?->name,
                'start_time' => $this->shift?->start_time,
                'end_time' => $this->shift?->end_time,
                'late_grace_period_minutes' => $this->shift?->late_grace_period_minutes,
            ]),
        ];
    }
}
