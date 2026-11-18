<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActionLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::LEAVE_APPROVE->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level' => ['nullable', 'integer', 'in:1,2,3'],
            'previous_leave_balance' => ['nullable', 'integer', 'min:0', 'max:365'],
            'current_leave_taken' => ['nullable', 'integer', 'min:0', 'max:365'],
            'remaining_leave_balance' => ['nullable', 'integer', 'min:-365', 'max:365'],
            'is_rejected' => ['nullable', 'boolean'],
            'hrd_other_notes' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('*.reject') || $this->boolean('is_rejected')),
                'nullable',
                'string',
                'min:3',
                'max:1000',
            ],
        ];
    }
}
