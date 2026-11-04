<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Attendance;

use App\Models\User;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\Attendance\Services\LeaveService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use App\Shared\Exceptions\DomainException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private LeaveService $leaveService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->leaveService = $this->app->make(LeaveService::class);
    }

    public function test_submit_leave_request_reserves_pending_quota_and_records_audit_trail(): void
    {
        $employee = Employee::factory()->create();
        $this->actingAs($employee->user);

        $annualType = LeaveType::factory()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'default_quota_days' => 12,
            'is_paid' => true,
            'requires_attachment' => false,
        ]);

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 0,
            'pending_quota' => 0,
        ]);

        $leaveRequest = $this->leaveService->submitRequest($employee->id, [
            'leave_type_id' => $annualType->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'total_days' => 3,
            'reason' => 'Family vacation out of town',
        ]);

        $this->assertSame(LeaveRequestStatus::PENDING, $leaveRequest->status);
        $this->assertSame(3, $leaveRequest->total_days);
        $this->assertSame($employee->user_id, $leaveRequest->created_by);

        $balance->refresh();
        $this->assertSame(0, $balance->used_quota);
        $this->assertSame(3, $balance->pending_quota);
        $this->assertSame(9, $balance->remainingQuota());

        $auditLog = AuditLog::query()
            ->where('event', 'leave_request.submitted')
            ->where('auditable_id', (string) $leaveRequest->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame(3, $auditLog->new_values['total_days']);
    }

    public function test_submit_leave_request_is_rejected_when_leave_quota_is_insufficient(): void
    {
        $employee = Employee::factory()->create();
        $annualType = LeaveType::factory()->create(['code' => 'ANNUAL']);

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 8,
            'pending_quota' => 2,
        ]);

        try {
            $this->leaveService->submitRequest($employee->id, [
                'leave_type_id' => $annualType->id,
                'start_date' => '2026-11-16',
                'end_date' => '2026-11-18',
                'total_days' => 3,
                'reason' => 'Extended personal trip',
            ]);
            $this->fail('Expected DomainException for insufficient leave quota was not thrown.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Insufficient leave quota', $exception->getMessage());
        }

        $balance->refresh();
        $this->assertSame(8, $balance->used_quota);
        $this->assertSame(2, $balance->pending_quota);
        $this->assertSame(0, LeaveRequest::query()->count());
    }

    public function test_quota_locking_prevents_over_allocation_overlap_and_enforces_attachment_rules(): void
    {
        $employee = Employee::factory()->create();
        $sickType = LeaveType::factory()->create([
            'code' => 'SICK',
            'name' => 'Sick Leave',
            'default_quota_days' => 5,
            'requires_attachment' => true,
        ]);

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $sickType->id,
            'year' => 2026,
            'total_quota' => 5,
            'used_quota' => 0,
            'pending_quota' => 0,
        ]);

        // Missing required medical certificate attachment -> fails atomically
        try {
            $this->leaveService->submitRequest($employee->id, [
                'leave_type_id' => $sickType->id,
                'start_date' => '2026-11-05',
                'end_date' => '2026-11-06',
                'total_days' => 2,
                'reason' => 'Flu recovery',
            ]);
            $this->fail('Expected attachment requirement exception was not thrown.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('requires a supporting attachment', $exception->getMessage());
        }

        // First valid 3-day request succeeds and locks 3 pending days (remaining: 2)
        $firstRequest = $this->leaveService->submitRequest($employee->id, [
            'leave_type_id' => $sickType->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-07',
            'total_days' => 3,
            'reason' => 'Medical treatment with doctor note',
            'attachment_path' => 'attachments/sick-note-1.pdf',
        ]);
        $this->assertSame(LeaveRequestStatus::PENDING, $firstRequest->status);

        // Overlapping date request is blocked even when 2 days remain
        try {
            $this->leaveService->submitRequest($employee->id, [
                'leave_type_id' => $sickType->id,
                'start_date' => '2026-11-07',
                'end_date' => '2026-11-08',
                'total_days' => 2,
                'reason' => 'Follow-up checkup',
                'attachment_path' => 'attachments/sick-note-2.pdf',
            ]);
            $this->fail('Expected overlapping leave exception was not thrown.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('overlapping', $exception->getMessage());
        }

        // Competing 3-day request on non-overlapping dates cannot exceed locked balance (3 + 3 > 5)
        try {
            $this->leaveService->submitRequest($employee->id, [
                'leave_type_id' => $sickType->id,
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-22',
                'total_days' => 3,
                'reason' => 'Post-treatment rest',
                'attachment_path' => 'attachments/sick-note-3.pdf',
            ]);
            $this->fail('Expected quota over-allocation exception was not thrown.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Insufficient leave quota', $exception->getMessage());
        }

        // Exact remaining 2-day request succeeds cleanly (pending_quota = 5, remaining = 0)
        $secondRequest = $this->leaveService->submitRequest($employee->id, [
            'leave_type_id' => $sickType->id,
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
            'total_days' => 2,
            'reason' => 'Scheduled therapy',
            'attachment_path' => 'attachments/sick-note-4.pdf',
        ]);
        $this->assertSame(LeaveRequestStatus::PENDING, $secondRequest->status);

        $balance->refresh();
        $this->assertSame(5, $balance->pending_quota);
        $this->assertSame(0, $balance->used_quota);
        $this->assertSame(0, $balance->remainingQuota());
    }

    public function test_approval_workflow_transitions_status_to_approved_and_increments_used_quota(): void
    {
        $managerUser = User::factory()->create();
        $managerUser->assignRole(RoleName::MANAGER->value);
        $managerEmployee = Employee::factory()->create(['user_id' => $managerUser->id]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole(RoleName::EMPLOYEE->value);
        $staffEmployee = Employee::factory()->create([
            'user_id' => $staffUser->id,
            'manager_id' => $managerEmployee->id,
        ]);

        $annualType = LeaveType::factory()->create(['code' => 'ANNUAL']);
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $staffEmployee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 2,
            'pending_quota' => 0,
        ]);

        $leaveRequest = $this->leaveService->submitRequest($staffEmployee->id, [
            'leave_type_id' => $annualType->id,
            'start_date' => '2026-11-09',
            'end_date' => '2026-11-12',
            'total_days' => 4,
            'reason' => 'Annual family leave',
        ]);

        $this->assertSame(4, $balance->fresh()?->pending_quota);

        $approved = $this->leaveService->approveRequest($leaveRequest->id, $managerUser->id);

        $this->assertSame(LeaveRequestStatus::APPROVED, $approved->status);
        $this->assertSame($managerUser->id, $approved->approver_id);
        $this->assertNotNull($approved->approved_at);

        $balance->refresh();
        $this->assertSame(0, $balance->pending_quota);
        $this->assertSame(6, $balance->used_quota);
        $this->assertSame(6, $balance->remainingQuota());

        $auditLog = AuditLog::query()
            ->where('event', 'leave_request.approved')
            ->where('auditable_id', (string) $leaveRequest->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($managerUser->id, $auditLog->actor_id);
    }

    public function test_rejection_workflow_restores_pending_quota_and_records_reason(): void
    {
        $hrUser = User::factory()->create();
        $hrUser->assignRole(RoleName::HR_ADMIN->value);

        $employee = Employee::factory()->create();
        $annualType = LeaveType::factory()->create(['code' => 'ANNUAL']);
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 0,
            'pending_quota' => 0,
        ]);

        $leaveRequest = $this->leaveService->submitRequest($employee->id, [
            'leave_type_id' => $annualType->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-04',
            'total_days' => 4,
            'reason' => 'Year-end holiday',
        ]);

        $this->assertSame(4, $balance->fresh()?->pending_quota);

        $rejected = $this->leaveService->rejectRequest(
            leaveRequestId: $leaveRequest->id,
            approverId: $hrUser->id,
            rejectionReason: 'Critical year-end deployment freeze',
        );

        $this->assertSame(LeaveRequestStatus::REJECTED, $rejected->status);
        $this->assertSame($hrUser->id, $rejected->approver_id);
        $this->assertSame('Critical year-end deployment freeze', $rejected->rejection_reason);
        $this->assertNull($rejected->approved_at);

        $balance->refresh();
        $this->assertSame(0, $balance->pending_quota);
        $this->assertSame(0, $balance->used_quota);
        $this->assertSame(12, $balance->remainingQuota());
    }

    public function test_http_endpoints_and_policy_enforce_ownership_and_manager_hr_authorization(): void
    {
        $directManagerUser = User::factory()->create();
        $directManagerUser->assignRole(RoleName::MANAGER->value);
        $directManagerEmployee = Employee::factory()->create(['user_id' => $directManagerUser->id]);

        $otherManagerUser = User::factory()->create();
        $otherManagerUser->assignRole(RoleName::MANAGER->value);
        Employee::factory()->create(['user_id' => $otherManagerUser->id]);

        $hrAdminUser = User::factory()->create();
        $hrAdminUser->assignRole(RoleName::HR_ADMIN->value);

        $employeeUserA = User::factory()->create();
        $employeeUserA->assignRole(RoleName::EMPLOYEE->value);
        $employeeA = Employee::factory()->create([
            'user_id' => $employeeUserA->id,
            'manager_id' => $directManagerEmployee->id,
        ]);

        $employeeUserB = User::factory()->create();
        $employeeUserB->assignRole(RoleName::EMPLOYEE->value);
        $employeeB = Employee::factory()->create(['user_id' => $employeeUserB->id]);

        $annualType = LeaveType::factory()->create(['code' => 'ANNUAL']);
        LeaveBalance::factory()->create([
            'employee_id' => $employeeA->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
        ]);

        // Employee A submits their own leave request via HTTP (201 Created)
        $createResponse = $this->actingAs($employeeUserA)
            ->postJson(route('attendance.leave-requests.store'), [
                'leave_type_id' => $annualType->id,
                'start_date' => '2026-11-10',
                'end_date' => '2026-11-11',
                'total_days' => 2,
                'reason' => 'Personal family event',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', LeaveRequestStatus::PENDING->value)
            ->assertJsonPath('data.total_days', 2);

        $leaveRequestId = (int) $createResponse->json('data.id');

        // Employee A cannot submit on behalf of Employee B (403 Forbidden)
        $this->actingAs($employeeUserA)
            ->postJson(route('attendance.leave-requests.store'), [
                'employee_id' => $employeeB->id,
                'leave_type_id' => $annualType->id,
                'start_date' => '2026-11-15',
                'end_date' => '2026-11-16',
                'reason' => 'Unauthorized submission',
            ])
            ->assertForbidden();

        // Employee A can view their own leave request (200), but Employee B & unrelated Manager cannot (403)
        $this->actingAs($employeeUserA)
            ->getJson(route('attendance.leave-requests.show', $leaveRequestId))
            ->assertOk();

        $this->actingAs($employeeUserB)
            ->getJson(route('attendance.leave-requests.show', $leaveRequestId))
            ->assertForbidden();

        $this->actingAs($otherManagerUser)
            ->getJson(route('attendance.leave-requests.show', $leaveRequestId))
            ->assertForbidden();

        // Self-approval, colleague approval, and unrelated manager approval are all blocked (403 Forbidden)
        $this->actingAs($employeeUserA)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId))
            ->assertForbidden();

        $this->actingAs($employeeUserB)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId))
            ->assertForbidden();

        $this->actingAs($otherManagerUser)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId))
            ->assertForbidden();

        // Direct Manager can approve Employee A's leave request (200 OK)
        $this->actingAs($directManagerUser)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId))
            ->assertOk()
            ->assertJsonPath('data.status', LeaveRequestStatus::APPROVED->value)
            ->assertJsonPath('data.approver_id', $directManagerUser->id);

        // Submit a second request for Employee A and verify HR Admin rejection workflow + validation
        $secondResponse = $this->actingAs($employeeUserA)
            ->postJson(route('attendance.leave-requests.store'), [
                'leave_type_id' => $annualType->id,
                'start_date' => '2026-11-24',
                'end_date' => '2026-11-25',
                'total_days' => 2,
                'reason' => 'Additional leave request',
            ])
            ->assertCreated();

        $secondRequestId = (int) $secondResponse->json('data.id');

        // Rejecting without rejection_reason fails FormRequest validation (422)
        $this->actingAs($hrAdminUser)
            ->postJson(route('attendance.leave-requests.reject', $secondRequestId), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rejection_reason']);

        // HR Admin rejects with valid reason -> status REJECTED & pending_quota restored to 0
        $this->actingAs($hrAdminUser)
            ->postJson(route('attendance.leave-requests.reject', $secondRequestId), [
                'rejection_reason' => 'Peak operational period',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', LeaveRequestStatus::REJECTED->value);

        $balance = LeaveBalance::query()
            ->where('employee_id', $employeeA->id)
            ->where('leave_type_id', $annualType->id)
            ->firstOrFail();

        $this->assertSame(2, $balance->used_quota);
        $this->assertSame(0, $balance->pending_quota);
    }
}
