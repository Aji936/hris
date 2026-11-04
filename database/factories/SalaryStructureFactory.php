<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Payroll\Models\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryStructure>
 */
class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Structure '.$this->faker->unique()->bothify('GRADE-##??'),
            'description' => $this->faker->sentence(8),
            'is_active' => true,
        ];
    }
}
