<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Requests;

use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryStructureRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150', 'unique:salary_structures,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'components' => ['nullable', 'array'],
            'components.*.payroll_component_id' => ['required', 'integer', 'distinct', 'exists:payroll_components,id'],
            'components.*.default_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
        ];
    }
}
