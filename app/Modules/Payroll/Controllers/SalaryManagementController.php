<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Contracts\EmployeeSalaryRepositoryInterface;
use App\Modules\Payroll\Contracts\PayrollComponentRepositoryInterface;
use App\Modules\Payroll\Contracts\SalaryStructureRepositoryInterface;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollComponent;
use App\Modules\Payroll\Models\SalaryStructure;
use App\Modules\Payroll\Requests\AssignEmployeeSalaryRequest;
use App\Modules\Payroll\Requests\StorePayrollComponentRequest;
use App\Modules\Payroll\Requests\StoreSalaryStructureRequest;
use App\Modules\Payroll\Resources\EmployeeSalaryResource;
use App\Modules\Payroll\Resources\PayrollComponentResource;
use App\Modules\Payroll\Resources\SalaryStructureResource;
use App\Modules\Payroll\Services\SalaryAssignmentService;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SalaryManagementController extends Controller
{
    public function __construct(
        private readonly PayrollComponentRepositoryInterface $componentRepository,
        private readonly SalaryStructureRepositoryInterface $structureRepository,
        private readonly EmployeeSalaryRepositoryInterface $employeeSalaryRepository,
        private readonly SalaryAssignmentService $salaryAssignmentService,
    ) {}

    public function listComponents(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayrollComponent::class);

        $components = $this->componentRepository->paginateComponents(
            $request->only(['type', 'is_active']),
        );

        return PayrollComponentResource::collection($components)->response();
    }

    public function storeComponent(StorePayrollComponentRequest $request): JsonResponse
    {
        Gate::authorize('create', PayrollComponent::class);

        try {
            $component = $this->salaryAssignmentService->createComponent($request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'payroll_component' => [$exception->getMessage()],
            ]);
        }

        return (new PayrollComponentResource($component))
            ->response()
            ->setStatusCode(201);
    }

    public function listStructures(): JsonResponse
    {
        Gate::authorize('viewAny', SalaryStructure::class);

        $structures = $this->structureRepository->paginateStructures();

        return SalaryStructureResource::collection($structures)->response();
    }

    public function storeStructure(StoreSalaryStructureRequest $request): JsonResponse
    {
        Gate::authorize('create', SalaryStructure::class);

        try {
            $structure = $this->salaryAssignmentService->createStructure($request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'salary_structure' => [$exception->getMessage()],
            ]);
        }

        return (new SalaryStructureResource($structure))
            ->response()
            ->setStatusCode(201);
    }

    public function listEmployeeSalaries(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeSalary::class);

        $salaries = $this->employeeSalaryRepository->paginateSalaries(
            $request->only(['employee_id', 'is_active']),
        );

        return EmployeeSalaryResource::collection($salaries)->response();
    }

    public function assignEmployeeSalary(AssignEmployeeSalaryRequest $request): JsonResponse
    {
        Gate::authorize('create', EmployeeSalary::class);

        $validated = $request->validated();

        try {
            $assigned = $this->salaryAssignmentService->assignSalary(
                employeeId: $validated['employee_id'],
                data: $validated,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'employee_salary' => [$exception->getMessage()],
            ]);
        }

        return (new EmployeeSalaryResource($assigned))
            ->response()
            ->setStatusCode(201);
    }
}
