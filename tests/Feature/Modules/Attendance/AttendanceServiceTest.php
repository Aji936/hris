<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Attendance;

use App\Models\User;
use App\Modules\Attendance\Enums\AttendanceStatus;
use App\Modules\Attendance\Models\Shift;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use App\Shared\Exceptions\DomainException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $attendanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->attendanceService = $this->app->make(AttendanceService::class);
    }

    public function test_clock_in_on_time_and_within_grace_period_records_present_with_zero_late_minutes(): void
    {
        $shift = Shift::factory()->create([
            'name' => 'Morning Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_grace_period_minutes' => 15,
        ]);

        $employeeEarly = Employee::factory()->create(['shift_id' => $shift->id]);
        $employeeWithinGrace = Employee::factory()->create(['shift_id' => $shift->id]);

        $earlyRecord = $this->attendanceService->clockIn(
            employeeId: $employeeEarly->id,
            timestamp: '2026-11-04 08:50:00',
            ip: '10.0.0.11',
            location: '-6.200000,106.816666',
        );

        $this->assertSame(AttendanceStatus::PRESENT, $earlyRecord->status);
        $this->assertSame(0, $earlyRecord->late_minutes);
        $this->assertSame('10.0.0.11', $earlyRecord->clock_in_ip);

        $graceRecord = $this->attendanceService->clockIn(
            employeeId: $employeeWithinGrace->id,
            timestamp: '2026-11-04 09:15:00',
            ip: '10.0.0.12',
            location: '-6.200000,106.816666',
        );

        $this->assertSame(AttendanceStatus::PRESENT, $graceRecord->status);
        $this->assertSame(0, $graceRecord->late_minutes);
    }

    public function test_clock_in_after_grace_period_marks_late_and_calculates_late_minutes(): void
    {
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_grace_period_minutes' => 15,
        ]);

        $employee = Employee::factory()->create(['shift_id' => $shift->id]);
        $this->actingAs($employee->user);

        $lateRecord = $this->attendanceService->clockIn(
            employeeId: $employee->id,
            timestamp: '2026-11-04 09:27:00',
            ip: '192.168.1.25',
            location: '-6.175392,106.827153',
        );

        $this->assertSame(AttendanceStatus::LATE, $lateRecord->status);
        $this->assertSame(27, $lateRecord->late_minutes);
        $this->assertSame($employee->user_id, $lateRecord->created_by);

        $auditLog = AuditLog::query()
            ->where('event', 'attendance.clocked_in')
            ->where('auditable_id', (string) $lateRecord->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame(27, $auditLog->new_values['late_minutes']);
    }

    public function test_duplicate_clock_in_on_same_date_is_prevented(): void
    {
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create(['shift_id' => $shift->id]);

        $this->attendanceService->clockIn(
            employeeId: $employee->id,
            timestamp: '2026-11-04 08:58:00',
            ip: '127.0.0.1',
            location: 'HQ',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Employee has already clocked in on 2026-11-04.');

        $this->attendanceService->clockIn(
            employeeId: $employee->id,
            timestamp: '2026-11-04 09:05:00',
            ip: '127.0.0.1',
            location: 'HQ',
        );
    }

    public function test_clock_out_calculates_work_duration_minutes_and_early_leave_minutes(): void
    {
        $shift = Shift::factory()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_grace_period_minutes' => 10,
        ]);

        // Employee 1: Full shift (09:00 -> 17:10 = 490 work minutes, 0 early leave)
        $employeeFull = Employee::factory()->create(['shift_id' => $shift->id]);
        $this->attendanceService->clockIn($employeeFull->id, '2026-11-04 09:00:00', '127.0.0.1', 'HQ');

        $fullCompleted = $this->attendanceService->clockOut(
            employeeId: $employeeFull->id,
            timestamp: '2026-11-04 17:10:00',
            ip: '127.0.0.1',
            location: 'HQ',
        );

        $this->assertSame(490, $fullCompleted->work_duration_minutes);
        $this->assertSame(0, $fullCompleted->early_leave_minutes);

        // Employee 2: Early leave (09:00 -> 16:15 = 435 work minutes, 45 early leave minutes)
        $employeeEarlyLeave = Employee::factory()->create(['shift_id' => $shift->id]);
        $this->attendanceService->clockIn($employeeEarlyLeave->id, '2026-11-04 09:00:00', '127.0.0.1', 'HQ');

        $earlyCompleted = $this->attendanceService->clockOut(
            employeeId: $employeeEarlyLeave->id,
            timestamp: '2026-11-04 16:15:00',
            ip: '127.0.0.2',
            location: 'HQ',
        );

        $this->assertSame(435, $earlyCompleted->work_duration_minutes);
        $this->assertSame(45, $earlyCompleted->early_leave_minutes);
        $this->assertSame('127.0.0.2', $earlyCompleted->clock_out_ip);
    }

    public function test_http_clock_in_and_clock_out_endpoints_enforce_validation_and_rbac(): void
    {
        $shift = Shift::factory()->create([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'late_grace_period_minutes' => 5,
        ]);

        $userA = User::factory()->create();
        $userA->assignRole(RoleName::EMPLOYEE->value);
        $employeeA = Employee::factory()->create(['user_id' => $userA->id, 'shift_id' => $shift->id]);

        $userB = User::factory()->create();
        $userB->assignRole(RoleName::EMPLOYEE->value);
        $employeeB = Employee::factory()->create(['user_id' => $userB->id, 'shift_id' => $shift->id]);

        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        // Employee A clocks in for themselves (201 Created)
        $this->actingAs($userA)
            ->postJson(route('attendance.clock-in'), [
                'timestamp' => '2026-11-04 08:12:00',
                'location' => '-6.2088,106.8456',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AttendanceStatus::LATE->value)
            ->assertJsonPath('data.late_minutes', 12);

        // Duplicate HTTP clock-in on same date returns 422 validation error
        $this->actingAs($userA)
            ->postJson(route('attendance.clock-in'), [
                'timestamp' => '2026-11-04 08:20:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendance']);

        // Employee A cannot clock in on behalf of Employee B (403 Forbidden)
        $this->actingAs($userA)
            ->postJson(route('attendance.clock-in'), [
                'employee_id' => $employeeB->id,
                'timestamp' => '2026-11-04 08:00:00',
            ])
            ->assertForbidden();

        // Employee A clocks out for themselves (200 OK)
        $this->actingAs($userA)
            ->postJson(route('attendance.clock-out'), [
                'timestamp' => '2026-11-04 16:12:00',
                'location' => '-6.2088,106.8456',
            ])
            ->assertOk()
            ->assertJsonPath('data.work_duration_minutes', 480)
            ->assertJsonPath('data.early_leave_minutes', 0);

        // Regular employee cannot list global attendance records (403), whereas HR Admin can (200)
        $this->actingAs($userA)
            ->getJson(route('attendance.records.index'))
            ->assertForbidden();

        $this->actingAs($hrAdmin)
            ->getJson(route('attendance.records.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
