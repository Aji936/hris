<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Attendance\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Regular Office Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_grace_period_minutes' => 15,
            'is_active' => true,
        ];
    }
}
