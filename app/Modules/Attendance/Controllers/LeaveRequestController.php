<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Attendance\Contracts\LeaveBalanceRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveRequestRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveTypeRepositoryInterface;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\Attendance\Requests\ActionLeaveRequest;
use App\Modules\Attendance\Requests\SubmitLeaveRequest;
use App\Modules\Attendance\Resources\LeaveRequestResource;
use App\Modules\Attendance\Services\LeavePermitPdfService;
use App\Modules\Attendance\Services\LeaveService;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly LeaveTypeRepositoryInterface $leaveTypeRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly LeaveService $leaveService,
        private readonly LeavePermitPdfService $leavePermitPdfService,
    ) {}

    public function index(Request $request): JsonResponse|InertiaResponse
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        /** @var User $actor */
        $actor = $request->user();
        $filters = $request->only(['employee_id', 'status', 'leave_type_id']);
        $records = $this->leaveRequestRepository->paginateForActor($actor, $filters);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return LeaveRequestResource::collection($records)->response();
        }

        $employee = $this->employeeRepository->findByUserId($actor->id);
        $currentYear = (int) now()->year;

        $balances = $employee !== null
            ? $this->leaveBalanceRepository
                ->getBalancesForEmployeeYear($employee->id, $currentYear)
                ->map(static fn (LeaveBalance $balance): array => [
                    'id' => $balance->id,
                    'leave_type_id' => $balance->leave_type_id,
                    'year' => $balance->year,
                    'total_quota' => $balance->total_quota,
                    'used_quota' => $balance->used_quota,
                    'pending_quota' => $balance->pending_quota,
                    'remaining_quota' => $balance->remainingQuota(),
                    'leave_type' => $balance->leaveType === null ? null : [
                        'id' => $balance->leaveType->id,
                        'code' => $balance->leaveType->code,
                        'name' => $balance->leaveType->name,
                        'is_paid' => $balance->leaveType->is_paid,
                        'requires_attachment' => $balance->leaveType->requires_attachment,
                    ],
                ])
                ->values()
                ->all()
            : [];

        $leaveTypes = $this->leaveTypeRepository
            ->getActiveTypes()
            ->map(static fn (LeaveType $type): array => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'default_quota_days' => $type->default_quota_days,
                'is_paid' => $type->is_paid,
                'requires_attachment' => $type->requires_attachment,
            ])
            ->values()
            ->all();

        $isHrd = $actor->hasRole([RoleName::HR_ADMIN->value, RoleName::SUPERADMIN->value])
            || $actor->can(PermissionName::EMPLOYEE_UPDATE->value);

        $employeeLeaveBalances = [];
        if ($isHrd) {
            $annualType = $this->leaveService->resolveAnnualLeaveType();

            $activeEmployees = Employee::query()
                ->with([
                    'department:id,code,name',
                    'position:id,code,title',
                    'leaveBalances' => static fn ($q) => $q
                        ->where('year', $currentYear)
                        ->where('leave_type_id', $annualType->id),
                ])
                ->whereIn('status', [
                    EmploymentStatus::PERMANENT->value,
                    EmploymentStatus::PROBATION->value,
                    EmploymentStatus::CONTRACT->value,
                ])
                ->orderBy('employee_code')
                ->get();

            $employeeLeaveBalances = $activeEmployees->map(static function (Employee $emp) use ($currentYear): array {
                /** @var LeaveBalance|null $bal */
                $bal = $emp->leaveBalances->first();

                return [
                    'employee_id' => $emp->id,
                    'employee_code' => $emp->employee_code,
                    'full_name' => $emp->full_name,
                    'department_name' => $emp->department?->name ?? '-',
                    'position_title' => $emp->position?->title ?? '-',
                    'status' => $emp->status->value,
                    'year' => $currentYear,
                    'has_balance_record' => $bal !== null,
                    'leave_balance_id' => $bal?->id,
                    'total_quota' => $bal !== null ? $bal->total_quota : 0,
                    'used_quota' => $bal !== null ? $bal->used_quota : 0,
                    'pending_quota' => $bal !== null ? $bal->pending_quota : 0,
                    'remaining_quota' => $bal !== null ? $bal->remainingQuota() : 0,
                ];
            })->values()->all();
        }

        return Inertia::render('Attendance/Leave/Index', [
            'currentYear' => $currentYear,
            'hasEmployeeProfile' => $employee !== null,
            'employeeProfile' => $employee === null ? null : [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'join_date' => $employee->join_date?->toDateString(),
                'department_name' => $employee->department?->name,
                'position_title' => $employee->position?->title,
            ],
            'canApprove' => $actor->can(PermissionName::LEAVE_APPROVE->value),
            'isHrd' => $isHrd,
            'initialTab' => (string) $request->query('tab', 'requests'),
            'leaveBalances' => $balances,
            'employeeLeaveBalances' => $employeeLeaveBalances,
            'leaveTypes' => $leaveTypes,
            'leaveRequests' => LeaveRequestResource::collection($records->getCollection())->resolve(),
        ]);
    }

    public function leaveBalancesIndex(Request $request): JsonResponse|InertiaResponse
    {
        $request->merge(['tab' => 'balances']);

        return $this->index($request);
    }

    public function upsertLeaveBalance(Request $request, Employee $employee): JsonResponse|RedirectResponse
    {
        abort_unless(
            (bool) ($request->user()?->hasRole([RoleName::HR_ADMIN->value, RoleName::SUPERADMIN->value])
                || $request->user()?->can(PermissionName::EMPLOYEE_UPDATE->value)),
            403,
        );

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'total_quota' => ['required', 'numeric', 'min:0', 'max:365'],
            'used_quota' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'pending_quota' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'adjustment_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $balance = $this->leaveService->adjustEmployeeLeaveBalance($employee, $validated);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Saldo cuti karyawan berhasil diperbarui.',
                'data' => [
                    'id' => $balance->id,
                    'employee_id' => $balance->employee_id,
                    'year' => $balance->year,
                    'total_quota' => $balance->total_quota,
                    'used_quota' => $balance->used_quota,
                    'pending_quota' => $balance->pending_quota,
                    'remaining_quota' => $balance->remainingQuota(),
                ],
            ]);
        }

        return redirect()->back()->with('success', "Saldo cuti tahunan {$employee->full_name} berhasil diperbarui.");
    }

    public function bulkInitializeLeaveBalances(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(
            (bool) ($request->user()?->hasRole([RoleName::HR_ADMIN->value, RoleName::SUPERADMIN->value])
                || $request->user()?->can(PermissionName::EMPLOYEE_UPDATE->value)),
            403,
        );

        $validated = $request->validate([
            'default_quota' => ['required', 'numeric', 'min:0', 'max:365'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'overwrite_existing' => ['nullable', 'boolean'],
            'adjustment_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $count = $this->leaveService->bulkInitializeAnnualLeaveBalances(
            defaultQuota: (float) $validated['default_quota'],
            year: isset($validated['year']) ? (int) $validated['year'] : (int) now()->year,
            overwriteExisting: (bool) ($validated['overwrite_existing'] ?? false),
            adjustmentReason: $validated['adjustment_reason'] ?? null,
        );

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => "Saldo awal cuti tahunan berhasil diinisialisasi untuk {$count} karyawan.",
                'data' => [
                    'initialized_count' => $count,
                ],
            ]);
        }

        return redirect()->back()->with('success', "Saldo awal cuti tahunan berhasil diinisialisasi untuk {$count} karyawan aktif.");
    }

    public function show(int|string $id): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('view', $leaveRequest);

        return (new LeaveRequestResource($leaveRequest))->response();
    }

    public function printPdf(int|string $id): Response
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('view', $leaveRequest);

        return $this->leavePermitPdfService->download($leaveRequest);
    }

    public function store(SubmitLeaveRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $targetEmployee = $this->resolveTargetEmployee($actor, $validated['employee_id'] ?? null);

        Gate::authorize('create', [LeaveRequest::class, $targetEmployee]);

        if ($request->hasFile('attachment')) {
            $storedPath = $request->file('attachment')?->store('leave-attachments', 'local');
            if (is_string($storedPath)) {
                $validated['attachment_path'] = $storedPath;
            }
        }

        try {
            $leaveRequest = $this->leaveService->submitRequest(
                employeeId: $targetEmployee->id,
                data: $validated,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'leave' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new LeaveRequestResource($leaveRequest))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->back()->with('success', 'Leave & Permit application submitted successfully.');
    }

    public function approve(ActionLeaveRequest $request, int|string $id): JsonResponse|RedirectResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('approve', $leaveRequest);

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $level = isset($validated['level']) ? (int) $validated['level'] : null;

        try {
            $updated = $this->leaveService->approveRequest(
                leaveRequestId: $leaveRequest->id,
                approverId: $actor->id,
                level: $level,
                hrdPayload: $validated,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'leave' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new LeaveRequestResource($updated))->response();
        }

        return redirect()->back()->with('success', 'Leave & Permit application updated successfully.');
    }

    public function reject(ActionLeaveRequest $request, int|string $id): JsonResponse|RedirectResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('reject', $leaveRequest);

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        try {
            $updated = $this->leaveService->rejectRequest(
                leaveRequestId: $leaveRequest->id,
                approverId: $actor->id,
                rejectionReason: (string) $validated['rejection_reason'],
                hrdOtherNotes: isset($validated['hrd_other_notes']) ? (string) $validated['hrd_other_notes'] : null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'leave' => [$exception->getMessage()],
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new LeaveRequestResource($updated))->response();
        }

        return redirect()->back()->with('success', 'Leave & Permit application rejected.');
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType): JsonResponse|RedirectResponse
    {
        abort_unless((bool) $request->user()?->can(PermissionName::EMPLOYEE_UPDATE->value), 403);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('leave_types', 'code')->ignore($leaveType->id)->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'default_quota_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->leaveService->updateLeaveType($leaveType, $validated);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'data' => $updated,
            ]);
        }

        return redirect()->back()->with('success', 'Leave type updated successfully.');
    }

    private function resolveTargetEmployee(User $actor, int|string|null $requestedEmployeeId): Employee
    {
        if ($requestedEmployeeId !== null) {
            /** @var Employee $employee */
            $employee = $this->employeeRepository->findOrFail($requestedEmployeeId);

            return $employee;
        }

        $linkedEmployee = $this->employeeRepository->findByUserId($actor->id);
        if ($linkedEmployee === null) {
            throw ValidationException::withMessages([
                'employee_id' => ['Authenticated user is not linked to an employee profile.'],
            ]);
        }

        return $linkedEmployee;
    }
}
