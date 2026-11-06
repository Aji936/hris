<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionName::EMPLOYEE_CREATE->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('employees', 'user_id')->whereNull('deleted_at'),
            ],
            'name' => ['required_without_all:user_id,full_name', 'nullable', 'string', 'max:150'],
            'full_name' => ['required_without_all:user_id,name', 'nullable', 'string', 'max:150'],
            'email' => [
                'required_without:user_id',
                'nullable',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'string', 'min:8'],

            'employee_code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('employees', 'employee_code')->whereNull('deleted_at'),
            ],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'join_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'string', Rule::enum(EmploymentStatus::class)],
            'salary_grade' => ['nullable', 'string', 'max:50'],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],

            'national_id_number' => ['nullable', 'string', 'max:32'],
            'tax_id_number' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
