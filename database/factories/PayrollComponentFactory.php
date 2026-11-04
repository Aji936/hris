<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollComponent>
 */
class PayrollComponentFactory extends Factory
{
    protected $model = PayrollComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('COMP_????')),
            'name' => $this->faker->words(2, true).' Allowance',
            'type' => PayrollComponentType::ALLOWANCE,
            'is_taxable' => true,
            'is_fixed' => true,
            'formula' => null,
            'is_active' => true,
        ];
    }
}
