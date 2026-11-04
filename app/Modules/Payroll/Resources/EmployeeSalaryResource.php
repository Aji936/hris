<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Modules\Payroll\Models\EmployeeSalary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeSalary
 */
class EmployeeSalaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'salary_structure_id' => $this->salary_structure_id,
            'effective_date' => $this->effective_date?->toDateString(),
            'base_salary' => (string) $this->base_salary,
            'payment_method' => $this->payment_method->value,
            'is_active' => $this->is_active,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee?->id,
                'employee_code' => $this->employee?->employee_code,
                'full_name' => $this->employee?->full_name,
            ]),
            'salary_structure' => new SalaryStructureResource($this->whenLoaded('salaryStructure')),
        ];
    }
}
