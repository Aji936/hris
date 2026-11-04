<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionName::EMPLOYEE_UPDATE->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeEmployee = $this->route('employee');
        $employeeId = $routeEmployee instanceof Employee ? $routeEmployee->id : $routeEmployee;

        $managerRules = ['nullable', 'integer', 'exists:employees,id'];
        if ($employeeId !== null) {
            $managerRules[] = Rule::notIn([(int) $employeeId]);
        }

        return [
            'employee_code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('employees', 'employee_code')
                    ->ignore($employeeId)
                    ->whereNull('deleted_at'),
            ],
            'full_name' => ['sometimes', 'required', 'string', 'max:150'],
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'required', 'integer', 'exists:positions,id'],
            'manager_id' => $managerRules,
            'join_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'required', 'string', Rule::enum(EmploymentStatus::class)],
            'salary_grade' => ['nullable', 'string', 'max:50'],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],

            'national_id_number' => ['nullable', 'string', 'max:32'],
            'tax_id_number' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
