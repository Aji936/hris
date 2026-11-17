<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Services\OrganizationMasterService;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationMasterController extends Controller
{
    public function __construct(
        private readonly OrganizationMasterService $organizationMasterService,
    ) {}

    public function updateDepartment(Request $request, Department $department): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $department);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('departments', 'code')->ignore($department->id)->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $updated = $this->organizationMasterService->updateDepartment($department, $validated);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'department' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'data' => $updated,
            ]);
        }

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function updatePosition(Request $request, Position $position): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $position);

        $validated = $request->validate([
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('positions', 'code')->ignore($position->id)->whereNull('deleted_at'),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'level' => ['sometimes', 'required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'base_salary_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'base_salary_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $updated = $this->organizationMasterService->updatePosition($position, $validated);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'position' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'data' => $updated,
            ]);
        }

        return redirect()->back()->with('success', 'Position updated successfully.');
    }
}
