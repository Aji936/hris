<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Requests;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollComponentRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:payroll_components,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in(PayrollComponentType::values())],
            'is_taxable' => ['sometimes', 'boolean'],
            'is_fixed' => ['sometimes', 'boolean'],
            'formula' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
