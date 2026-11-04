<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Contracts\DepartmentRepositoryInterface;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Requests\StoreEmployeeRequest;
use App\Modules\CoreHR\Requests\UpdateEmployeeRequest;
use App\Modules\CoreHR\Resources\EmployeeResource;
use App\Modules\CoreHR\Services\EmployeeOnboardingService;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
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

        if ($request->wantsJson()) {
            return EmployeeResource::collection($paginated)->response();
        }

        return Inertia::render('CoreHR/Employees/Index', [
            'employees' => EmployeeResource::collection($paginated),
            'departments' => $this->departmentRepository->allWithHierarchy(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        try {
            $employee = $this->employeeOnboardingService->onboard($request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'domain' => [$exception->getMessage()],
            ]);
        }

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        $loaded = $this->employeeRepository->findWithRelations($employee->id);

        return (new EmployeeResource($loaded))->response();
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        try {
            $updated = $this->employeeOnboardingService->update($employee, $request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'domain' => [$exception->getMessage()],
            ]);
        }

        return (new EmployeeResource($updated))->response();
    }

    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);

        $this->employeeOnboardingService->archive($employee);

        return response()->json([
            'message' => 'Employee archived successfully.',
        ]);
    }
}
