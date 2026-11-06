<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Enums\PayslipStatus;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_id' => Payroll::factory(),
            'employee_id' => Employee::factory(),
            'payslip_number' => strtoupper($this->faker->unique()->bothify('SLP-202611-####??')),
            'employee_snapshot' => [
                'employee_code' => 'EMP-0001',
                'full_name' => 'Snapshot Employee',
                'national_id_number' => '3171000000000001',
                'position_title' => 'Software Engineer',
                'department_name' => 'Engineering',
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
            ],
            'basic_salary' => '12000000.00',
            'total_allowance' => '1000000.00',
            'total_deduction' => '240000.00',
            'net_salary' => '12760000.00',
            'attendance_summary' => [
                'present_days' => 20,
                'late_days' => 0,
                'late_minutes' => 0,
                'sick_days' => 0,
                'leave_days' => 0,
                'unpaid_leave_days' => 0,
                'absent_days' => 0,
                'overtime_minutes' => 0,
            ],
            'status' => PayslipStatus::DRAFT,
        ];
    }
}
