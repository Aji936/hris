<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Requests;

use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollRequest extends FormRequest
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
            'payroll_period' => ['required', 'string', 'max:20', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'title' => ['nullable', 'string', 'max:150'],
            'async' => ['sometimes', 'boolean'],
        ];
    }
}
