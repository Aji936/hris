<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Payroll\Enums\PayrollStatus;
use App\Modules\Payroll\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_period' => $this->faker->unique()->numerify('202#-0#'),
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'title' => 'Payroll November 2026',
            'status' => PayrollStatus::GENERATED,
            'total_gross_amount' => '0.00',
            'total_deduction_amount' => '0.00',
            'total_net_amount' => '0.00',
            'generated_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
