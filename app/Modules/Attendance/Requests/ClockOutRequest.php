<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user->can(PermissionName::ATTENDANCE_CLOCK->value)
            || $user->can(PermissionName::ATTENDANCE_MANAGE->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'timestamp' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
