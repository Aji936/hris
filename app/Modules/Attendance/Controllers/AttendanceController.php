<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Attendance\Contracts\AttendanceRepositoryInterface;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Requests\ClockInRequest;
use App\Modules\Attendance\Requests\ClockOutRequest;
use App\Modules\Attendance\Resources\AttendanceResource;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Attendance::class);

        $filters = $request->only(['employee_id', 'date', 'status']);
        $records = $this->attendanceRepository->paginateRecords($filters);

        return AttendanceResource::collection($records)->response();
    }

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $targetEmployee = $this->resolveTargetEmployee($actor, $request->validated('employee_id'));

        Gate::authorize('clock', [Attendance::class, $targetEmployee]);

        try {
            $attendance = $this->attendanceService->clockIn(
                employeeId: $targetEmployee->id,
                timestamp: $request->validated('timestamp'),
                ip: $request->ip(),
                location: $request->validated('location'),
                shiftId: $request->validated('shift_id'),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'attendance' => [$exception->getMessage()],
            ]);
        }

        return (new AttendanceResource($attendance))
            ->response()
            ->setStatusCode(201);
    }

    public function clockOut(ClockOutRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $targetEmployee = $this->resolveTargetEmployee($actor, $request->validated('employee_id'));

        Gate::authorize('clock', [Attendance::class, $targetEmployee]);

        try {
            $attendance = $this->attendanceService->clockOut(
                employeeId: $targetEmployee->id,
                timestamp: $request->validated('timestamp'),
                ip: $request->ip(),
                location: $request->validated('location'),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'attendance' => [$exception->getMessage()],
            ]);
        }

        return (new AttendanceResource($attendance))->response();
    }

    private function resolveTargetEmployee(User $actor, int|string|null $requestedEmployeeId): Employee
    {
        if ($requestedEmployeeId !== null) {
            return $this->employeeRepository->findOrFail($requestedEmployeeId);
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
