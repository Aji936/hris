<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_code' => strtoupper($this->faker->unique()->bothify('EMP-2026-####')),
            'full_name' => $this->faker->name(),
            'department_id' => Department::factory(),
            'position_id' => static fn (array $attributes): int => Position::factory()->create([
                'department_id' => $attributes['department_id'],
            ])->id,
            'manager_id' => null,
            'join_date' => $this->faker->date('Y-m-d'),
            'status' => EmploymentStatus::PERMANENT,
            'salary_grade' => 'G-3',
            'basic_salary' => '10000000.00',
            'national_id_number' => $this->faker->numerify('3171############'),
            'tax_id_number' => $this->faker->numerify('09#############'),
            'bank_account_number' => $this->faker->numerify('123456####'),
            'bank_name' => 'BCA',
        ];
    }
}
