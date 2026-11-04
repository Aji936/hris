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
            'rejection_reason' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('*.reject')),
                'nullable',
                'string',
                'min:3',
                'max:1000',
            ],
        ];
    }
}
