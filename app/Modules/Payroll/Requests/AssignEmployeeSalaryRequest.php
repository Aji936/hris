<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Requests;

use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEmployeeSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::PAYROLL_CREATE->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'salary_structure_id' => ['required', 'integer', 'exists:salary_structures,id'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'payment_method' => ['sometimes', 'string', Rule::in(PaymentMethod::values())],
        ];
    }
}
