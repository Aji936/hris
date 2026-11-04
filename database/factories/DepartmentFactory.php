<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CoreHR\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('DEPT-???-##')),
            'name' => $this->faker->unique()->company().' Division',
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
