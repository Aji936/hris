<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Enums\MaritalStatus;
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

    protected function prepareForValidation(): void
    {
        $routeEmployee = $this->route('employee');
        $employee = $routeEmployee instanceof Employee
            ? $routeEmployee
            : ($routeEmployee !== null ? Employee::query()->find($routeEmployee) : null);

        if (! $this->has('date_of_birth')) {
            $this->merge([
                'date_of_birth' => $employee?->date_of_birth?->toDateString() ?? '1995-05-15',
            ]);
        }

        if ($this->input('retirement_date') === '') {
            $this->merge([
                'retirement_date' => null,
            ]);
        }

        if (! $this->has('marital_status')) {
            $this->merge([
                'marital_status' => $employee?->marital_status?->value ?? MaritalStatus::SINGLE->value,
            ]);
        }

        if (! $this->has('dependents_count')) {
            $this->merge([
                'dependents_count' => (int) ($employee?->dependents_count ?? 0),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeEmployee = $this->route('employee');
        $employee = $routeEmployee instanceof Employee
            ? $routeEmployee
            : ($routeEmployee !== null ? Employee::query()->find($routeEmployee) : null);

        $employeeId = $employee?->id ?? $routeEmployee;
        $linkedUserId = $employee?->user_id;

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
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'full_name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')
                    ->ignore($linkedUserId)
                    ->whereNull('deleted_at'),
            ],
            'date_of_birth' => ['required', 'date', 'before:-17 years'],
            'retirement_date' => ['nullable', 'date', 'after:date_of_birth'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'marital_status' => ['required', 'string', Rule::in(MaritalStatus::values())],
            'dependents_count' => ['required', 'integer', 'min:0', 'max:20'],

            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'required', 'integer', 'exists:positions,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'manager_id' => $managerRules,
            'join_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'required', 'string', Rule::enum(EmploymentStatus::class)],
            'salary_grade' => ['nullable', 'string', 'max:50'],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],

            'national_id_number' => ['nullable', 'string', 'max:32'],
            'tax_id_number' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'initial_leave_balance' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'annual_leave_quota' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ];
    }
}
