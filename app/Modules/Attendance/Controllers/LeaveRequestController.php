<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Attendance\Contracts\LeaveRequestRepositoryInterface;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Requests\ActionLeaveRequest;
use App\Modules\Attendance\Requests\SubmitLeaveRequest;
use App\Modules\Attendance\Resources\LeaveRequestResource;
use App\Modules\Attendance\Services\LeaveService;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly LeaveService $leaveService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        /** @var User $actor */
        $actor = $request->user();
        $filters = $request->only(['employee_id', 'status', 'leave_type_id']);
        $records = $this->leaveRequestRepository->paginateForActor($actor, $filters);

        return LeaveRequestResource::collection($records)->response();
    }

    public function show(int|string $id): JsonResponse
    {
        $leaveRequest = $this->leaveRequestRepository->findWithRelations($id);
        abort_if($leaveRequest === null, 404);

        Gate::authorize('view', $leaveRequest);

        return (new LeaveRequestResource($leaveRequest))->response();
    }

    public function store(SubmitLeaveRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $targetEmployee = $this->resolveTargetEmployee($actor, $request->validated('employee_id'));

        Gate::authorize('create', [LeaveRequest::class, $targetEmployee]);

        try {
            $leaveRequest = $this->leaveService->submitRequest(
                employeeId: $targetEmployee->id,
                data: $request->validated(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'leave' => [$exception->getMessage()],
            ]);
        }

        return (new LeaveRequestResource($leaveRequest))
            ->response()
            ->setStatusCode(201);
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
