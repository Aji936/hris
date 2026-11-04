<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    protected $model = EmployeeSalary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'salary_structure_id' => SalaryStructure::factory(),
            'effective_date' => '2026-11-01',
            'base_salary' => '12000000.00',
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'is_active' => true,
        ];
    }
}
