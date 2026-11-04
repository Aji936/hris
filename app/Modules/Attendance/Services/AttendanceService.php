<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Contracts\AttendanceRepositoryInterface;
use App\Modules\Attendance\Contracts\ShiftRepositoryInterface;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\Shift;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Services\AuditLogService;
use App\Shared\Exceptions\DomainException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository,
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Record daily employee clock-in, preventing duplicate check-ins on the same date
     * and calculating late minutes against the assigned shift schedule and grace period.
     */
    public function clockIn(
        int|string $employeeId,
        DateTimeInterface|string|null $timestamp = null,
        ?string $ip = null,
        ?string $location = null,
        int|string|null $shiftId = null,
    ): Attendance {
        $clockInAt = $this->parseTimestamp($timestamp);
        $attendanceDate = $clockInAt->toDateString();

        return DB::transaction(function () use ($employeeId, $clockInAt, $attendanceDate, $ip, $location, $shiftId): Attendance {
            $employee = $this->employeeRepository->findOrFail($employeeId);

            $existing = $this->attendanceRepository->findByEmployeeAndDate($employee->id, $attendanceDate, lockForUpdate: true);
            if ($existing !== null) {
                throw new DomainException("Employee has already clocked in on {$attendanceDate}.");
            }

            $shift = $this->resolveShift($employee, $shiftId);
            [$lateMinutes, $status] = $this->calculateClockInMetrics($shift, $attendanceDate, $clockInAt);

            /** @var Attendance $attendance */
            $attendance = $this->attendanceRepository->create([
                'employee_id' => $employee->id,
                'shift_id' => $shift->id,
                'date' => $attendanceDate,
                'clock_in' => $clockInAt,
                'clock_in_ip' => $ip,
                'clock_in_location' => $location,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => 0,
                'work_duration_minutes' => 0,
                'status' => $status->value,
            ]);

            $this->auditLogService->record(
                event: 'attendance.clocked_in',
                auditable: $attendance,
                oldValues: null,
                newValues: [
                    'employee_id' => $employee->id,
                    'shift_id' => $shift->id,
                    'date' => $attendanceDate,
                    'clock_in' => $clockInAt->toIso8601String(),
                    'late_minutes' => $lateMinutes,
                    'status' => $status->value,
                ],
            );

            return $attendance->load(['employee', 'shift']);
        });
    }

    /**
     * Record employee clock-out, computing work_duration_minutes and early_leave_minutes.
     */
    public function clockOut(
        int|string $employeeId,
        DateTimeInterface|string|null $timestamp = null,
        ?string $ip = null,
        ?string $location = null,
    ): Attendance {
        $clockOutAt = $this->parseTimestamp($timestamp);
        $targetDate = $clockOutAt->toDateString();

        return DB::transaction(function () use ($employeeId, $clockOutAt, $targetDate, $ip, $location): Attendance {
            $employee = $this->employeeRepository->findOrFail($employeeId);

            $attendance = $this->attendanceRepository->findByEmployeeAndDate($employee->id, $targetDate, lockForUpdate: true)
                ?? $this->attendanceRepository->findLatestOpenForEmployee($employee->id, lockForUpdate: true);

            if ($attendance === null || $attendance->clock_in === null) {
                throw new DomainException('Cannot clock out without an active clock-in record.');
            }

            if ($attendance->clock_out !== null) {
                throw new DomainException('Employee has already clocked out for this attendance date.');
            }

            $clockInAt = CarbonImmutable::instance($attendance->clock_in);
            if ($clockOutAt->lt($clockInAt)) {
                throw new DomainException('Clock-out timestamp cannot be earlier than clock-in timestamp.');
            }

            /** @var Shift $shift */
            $shift = $attendance->shift ?? $this->shiftRepository->findOrFail($attendance->shift_id);

            $workDurationMinutes = (int) floor($clockInAt->diffInSeconds($clockOutAt) / 60);
            $earlyLeaveMinutes = $this->calculateEarlyLeaveMinutes($shift, $attendance->date->toDateString(), $clockOutAt);

            /** @var Attendance $updated */
            $updated = $this->attendanceRepository->update($attendance, [
                'clock_out' => $clockOutAt,
                'clock_out_ip' => $ip,
                'clock_out_location' => $location,
                'work_duration_minutes' => $workDurationMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
            ]);

            $this->auditLogService->record(
                event: 'attendance.clocked_out',
                auditable: $updated,
                oldValues: ['clock_out' => null],
                newValues: [
                    'clock_out' => $clockOutAt->toIso8601String(),
                    'work_duration_minutes' => $workDurationMinutes,
                    'early_leave_minutes' => $earlyLeaveMinutes,
                ],
            );

            return $updated->load(['employee', 'shift']);
        });
    }

    private function resolveShift(Employee $employee, int|string|null $shiftId): Shift
    {
        if ($shiftId !== null) {
            return $this->shiftRepository->findOrFail($shiftId);
        }

        if ($employee->shift_id !== null) {
            return $this->shiftRepository->findOrFail($employee->shift_id);
        }

        $defaultShift = $this->shiftRepository->findDefaultActive();
        if ($defaultShift === null) {
            throw new DomainException('No active work shift is configured for this employee.');
        }

        return $defaultShift;
    }

    /**
     * @return array{0: int, 1: AttendanceStatus}
     */
    private function calculateClockInMetrics(Shift $shift, string $attendanceDate, CarbonInterface $clockInAt): array
    {
        $scheduledStart = CarbonImmutable::parse(
            $attendanceDate.' '.$shift->start_time,
            $clockInAt->getTimezone(),
        );

        $graceDeadline = $scheduledStart->addMinutes((int) $shift->late_grace_period_minutes);

        if ($clockInAt->lte($graceDeadline)) {
            return [0, AttendanceStatus::PRESENT];
        }

        $lateMinutes = (int) floor($scheduledStart->diffInSeconds($clockInAt) / 60);

        return [$lateMinutes, AttendanceStatus::LATE];
    }

    private function calculateEarlyLeaveMinutes(Shift $shift, string $attendanceDate, CarbonInterface $clockOutAt): int
    {
        $scheduledStart = CarbonImmutable::parse(
            $attendanceDate.' '.$shift->start_time,
            $clockOutAt->getTimezone(),
        );

        $scheduledEnd = CarbonImmutable::parse(
            $attendanceDate.' '.$shift->end_time,
            $clockOutAt->getTimezone(),
        );

        // Support overnight shifts where end_time is past midnight
        if ($scheduledEnd->lte($scheduledStart)) {
            $scheduledEnd = $scheduledEnd->addDay();
        }

        if ($clockOutAt->gte($scheduledEnd)) {
            return 0;
        }

        return (int) floor($clockOutAt->diffInSeconds($scheduledEnd) / 60);
    }

    private function parseTimestamp(DateTimeInterface|string|null $timestamp): CarbonImmutable
    {
        if ($timestamp === null) {
            return CarbonImmutable::now();
        }

        if ($timestamp instanceof DateTimeInterface) {
            return CarbonImmutable::instance($timestamp);
        }

        return CarbonImmutable::parse($timestamp);
    }
}
