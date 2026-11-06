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
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function portal(Request $request): InertiaResponse
    {
        Gate::authorize('viewSelf', Attendance::class);

        /** @var User $actor */
        $actor = $request->user();
        $employee = $this->employeeRepository->findByUserId($actor->id);
        $employee?->loadMissing(['shift', 'department', 'position']);

        $now = CarbonImmutable::now();
        $today = $now->toDateString();
        $startOfMonth = $now->startOfMonth()->toDateString();
        $endOfMonth = $now->endOfMonth()->toDateString();

        $todayRecord = $employee !== null
            ? $this->attendanceRepository->findByEmployeeAndDate($employee->id, $today)
            : null;

        $monthlyRecords = $employee !== null
            ? $this->attendanceRepository->getForEmployeeBetweenDates($employee->id, $startOfMonth, $endOfMonth)
            : collect();

        return Inertia::render('Attendance/Index', [
            'employee' => $employee === null ? null : [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department_name' => $employee->department?->name,
                'position_title' => $employee->position?->title,
                'shift' => $employee->shift === null ? null : [
                    'id' => $employee->shift->id,
                    'name' => $employee->shift->name,
                    'start_time' => $employee->shift->start_time,
                    'end_time' => $employee->shift->end_time,
                    'late_grace_period_minutes' => $employee->shift->late_grace_period_minutes,
                ],
            ],
            'todayAttendance' => $todayRecord !== null
                ? (new AttendanceResource($todayRecord))->resolve()
                : null,
            'monthlyRecords' => AttendanceResource::collection($monthlyRecords)->resolve(),
            'currentMonthLabel' => $now->format('F Y'),
            'serverTime' => $now->toIso8601String(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Attendance::class);

        $filters = $request->only(['employee_id', 'date', 'status']);
        $records = $this->attendanceRepository->paginateRecords($filters);

        return AttendanceResource::collection($records)->response();
    }

    public function clockIn(ClockInRequest $request): JsonResponse|RedirectResponse
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

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new AttendanceResource($attendance))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->back()->with('success', 'Clock-in recorded successfully.');
    }

    public function clockOut(ClockOutRequest $request): JsonResponse|RedirectResponse
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

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return (new AttendanceResource($attendance))->response();
        }

        return redirect()->back()->with('success', 'Clock-out recorded successfully.');
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
