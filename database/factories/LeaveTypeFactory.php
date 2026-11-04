<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Attendance\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('LV-????')),
            'name' => 'Annual Leave',
            'default_quota_days' => 12,
            'is_paid' => true,
            'requires_attachment' => false,
            'is_active' => true,
        ];
    }
}
