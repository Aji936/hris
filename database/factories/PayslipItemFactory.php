<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Models\PayrollComponent;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayslipItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayslipItem>
 */
class PayslipItemFactory extends Factory
{
    protected $model = PayslipItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payslip_id' => Payslip::factory(),
            'payroll_component_id' => PayrollComponent::factory(),
            'name' => 'Meal Allowance',
            'type' => PayrollComponentType::ALLOWANCE,
            'amount' => '1000000.00',
        ];
    }
}
