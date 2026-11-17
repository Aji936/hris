<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Contracts\EmployeeSalaryRepositoryInterface;
use App\Modules\Payroll\Contracts\PayrollComponentRepositoryInterface;
use App\Modules\Payroll\Contracts\SalaryStructureRepositoryInterface;
use App\Modules\Payroll\Enums\PaymentMethod;
use App\Modules\Payroll\Enums\PayrollComponentType;
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
use App\Modules\RBAC\Enums\PermissionName;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SalaryManagementController extends Controller
{
    public function __construct(
        private readonly PayrollComponentRepositoryInterface $componentRepository,
        private readonly SalaryStructureRepositoryInterface $structureRepository,
        private readonly EmployeeSalaryRepositoryInterface $employeeSalaryRepository,
        private readonly SalaryAssignmentService $salaryAssignmentService,
    ) {}

    public function portal(Request $request): InertiaResponse
    {
        Gate::authorize('viewAny', PayrollComponent::class);

        $components = $this->componentRepository->paginateComponents([], 100);
        $structures = $this->structureRepository->paginateStructures(100);
        $salaries = $this->employeeSalaryRepository->paginateSalaries(['is_active' => true], 100);

        return Inertia::render('Payroll/Management/Index', [
            'components' => PayrollComponentResource::collection($components->getCollection())->resolve(),
            'structures' => SalaryStructureResource::collection($structures->getCollection())->resolve(),
            'employeeSalaries' => EmployeeSalaryResource::collection($salaries->getCollection())->resolve(),
            'canManage' => (bool) $request->user()?->can(PermissionName::PAYROLL_CREATE->value),
        ]);
    }

    public function listComponents(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayrollComponent::class);

        $components = $this->componentRepository->paginateComponents(
            $request->only(['type', 'is_active']),
        );

        return PayrollComponentResource::collection($components)->response();
    }

    public function storeComponent(StorePayrollComponentRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', PayrollComponent::class);

        try {
            $component = $this->salaryAssignmentService->createComponent($request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'payroll_component' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new PayrollComponentResource($component))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->back()->with('success', 'Payroll component created successfully.');
    }

    public function updateComponent(Request $request, PayrollComponent $component): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $component);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('payroll_components', 'code')->ignore($component->id)->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'type' => ['sometimes', 'required', 'string', Rule::enum(PayrollComponentType::class)],
            'is_taxable' => ['sometimes', 'boolean'],
            'is_fixed' => ['sometimes', 'boolean'],
            'formula' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $updated = $this->salaryAssignmentService->updateComponent($component, $validated);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'payroll_component' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new PayrollComponentResource($updated))->response();
        }

        return redirect()->back()->with('success', 'Payroll component updated successfully.');
    }

    public function listStructures(): JsonResponse
    {
        Gate::authorize('viewAny', SalaryStructure::class);

        $structures = $this->structureRepository->paginateStructures();

        return SalaryStructureResource::collection($structures)->response();
    }

    public function storeStructure(StoreSalaryStructureRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', SalaryStructure::class);

        try {
            $structure = $this->salaryAssignmentService->createStructure($request->validated());
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'salary_structure' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new SalaryStructureResource($structure))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->back()->with('success', 'Salary structure created successfully.');
    }

    public function updateStructure(Request $request, SalaryStructure $structure): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $structure);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'components' => ['sometimes', 'array'],
            'components.*.payroll_component_id' => ['required_with:components', 'integer', 'exists:payroll_components,id'],
            'components.*.default_amount' => ['required_with:components', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        try {
            $updated = $this->salaryAssignmentService->updateStructure($structure, $validated);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'salary_structure' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new SalaryStructureResource($updated))->response();
        }

        return redirect()->back()->with('success', 'Salary structure updated successfully.');
    }

    public function listEmployeeSalaries(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeSalary::class);

        $salaries = $this->employeeSalaryRepository->paginateSalaries(
            $request->only(['employee_id', 'is_active']),
        );

        return EmployeeSalaryResource::collection($salaries)->response();
    }

    public function assignEmployeeSalary(AssignEmployeeSalaryRequest $request): JsonResponse|RedirectResponse
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

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new EmployeeSalaryResource($assigned))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->back()->with('success', 'Employee salary adjusted and effective record saved.');
    }

    public function updateEmployeeSalary(Request $request, EmployeeSalary $employeeSalary): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $employeeSalary);

        $validated = $request->validate([
            'salary_structure_id' => ['required', 'integer', 'exists:salary_structures,id'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'payment_method' => ['nullable', 'string', Rule::enum(PaymentMethod::class)],
        ]);

        try {
            $adjusted = $this->salaryAssignmentService->assignSalary(
                employeeId: $employeeSalary->employee_id,
                data: $validated,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'employee_salary' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new EmployeeSalaryResource($adjusted))
                ->response()
                ->setStatusCode(200);
        }

        return redirect()->back()->with('success', 'Employee salary adjusted and effective record saved.');
    }
}
