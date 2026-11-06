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
use App\Modules\Attendance\Services\LeaveService;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        return Inertia::render('Attendance/Leave/Index', [
            'currentYear' => $currentYear,
            'hasEmployeeProfile' => $employee !== null,
            'leaveBalances' => $balances,
            'leaveTypes' => $leaveTypes,
            'leaveRequests' => LeaveRequestResource::collection($records->getCollection())->resolve(),
        ]);
    }

    public function show(int|string $id): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('view', $leaveRequest);

        return (new LeaveRequestResource($leaveRequest))->response();
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

        return redirect()->back()->with('success', 'Leave request submitted successfully.');
    }

    public function approve(ActionLeaveRequest $request, int|string $id): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('approve', $leaveRequest);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $updated = $this->leaveService->approveRequest(
                leaveRequestId: $leaveRequest->id,
                approverId: $actor->id,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'leave' => [$exception->getMessage()],
            ]);
        }

        return (new LeaveRequestResource($updated))->response();
    }

    public function reject(ActionLeaveRequest $request, int|string $id): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('reject', $leaveRequest);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $updated = $this->leaveService->rejectRequest(
                leaveRequestId: $leaveRequest->id,
                approverId: $actor->id,
                rejectionReason: (string) $request->validated('rejection_reason'),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'leave' => [$exception->getMessage()],
            ]);
        }

        return (new LeaveRequestResource($updated))->response();
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
