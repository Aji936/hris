<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('POS-???-##')),
            'title' => $this->faker->jobTitle(),
            'level' => $this->faker->randomElement(['Staff', 'Senior', 'Lead', 'Manager']),
            'description' => $this->faker->optional()->sentence(),
            'base_salary_min' => '7000000.00',
            'base_salary_max' => '15000000.00',
            'is_active' => true,
        ];
    }
}
