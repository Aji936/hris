<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\CoreHR\Contracts\DepartmentRepositoryInterface;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Requests\StoreEmployeeRequest;
use App\Modules\CoreHR\Requests\UpdateEmployeeRequest;
use App\Modules\CoreHR\Resources\EmployeeResource;
use App\Modules\CoreHR\Services\EmployeeOnboardingService;
use App\Modules\RBAC\Enums\PermissionName;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly DepartmentRepositoryInterface $departmentRepository,
        private readonly EmployeeOnboardingService $employeeOnboardingService,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $request->only(['department_id', 'status', 'search']);
        $paginated = $this->employeeRepository->paginateDirectory($filters);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return EmployeeResource::collection($paginated)->response();
        }

        $actor = $request->user();

        return Inertia::render('CoreHR/Employees/Index', [
            'employees' => EmployeeResource::collection($paginated),
            'departments' => $this->departmentRepository->allWithHierarchy(),
            'positions' => Position::query()
                ->orderBy('title')
                ->get(['id', 'department_id', 'code', 'title', 'level', 'description', 'base_salary_min', 'base_salary_max', 'is_active']),
            'leaveTypes' => LeaveType::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'default_quota_days', 'is_paid', 'requires_attachment', 'is_active']),
            'managerOptions' => Employee::query()
                ->orderBy('full_name')
                ->get(['id', 'employee_code', 'full_name', 'department_id']),
            'canManage' => (bool) $actor?->can(PermissionName::EMPLOYEE_UPDATE->value),
            'filters' => $filters,
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        try {
            $employee = $this->employeeOnboardingService->onboard($request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'domain' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new EmployeeResource($employee))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->back()->with('success', 'Employee onboarded successfully.');
    }

    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        $loaded = $this->employeeRepository->findWithRelations($employee->id);

        return (new EmployeeResource($loaded))->response();
    }

    public function edit(Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        $loaded = $this->employeeRepository->findWithRelations($employee->id);

        return (new EmployeeResource($loaded))->response();
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $employee);

        try {
            $updated = $this->employeeOnboardingService->update($employee, $request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'domain' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new EmployeeResource($updated))->response();
        }

        return redirect()->back()->with('success', 'Employee record updated successfully.');
    }

    public function destroy(Employee $employee): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $employee);

        $this->employeeOnboardingService->archive($employee);

        if (request()->expectsJson() && ! request()->header('X-Inertia')) {
            return response()->json([
                'message' => 'Employee archived successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Employee archived successfully.');
    }
}
