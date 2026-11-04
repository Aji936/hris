<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\Attendance\Contracts\LeaveBalanceRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveRequestRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveTypeRepositoryInterface;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Services\AuditLogService;
use App\Shared\Exceptions\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly LeaveTypeRepositoryInterface $leaveTypeRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Submit a leave request while locking the employee's annual leave balance row
     * to prevent race conditions and reserve pending quota atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitRequest(int|string $employeeId, array $data): LeaveRequest
    {
        $startDate = CarbonImmutable::parse((string) $data['start_date'])->startOfDay();
        $endDate = CarbonImmutable::parse((string) $data['end_date'])->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new DomainException('Leave end date must be on or after the start date.');
        }

        if ($startDate->year !== $endDate->year) {
            throw new DomainException('Cross-year leave requests must be submitted separately per calendar year.');
        }

        $calendarSpanDays = (int) ($startDate->diffInDays($endDate) + 1);
        $totalDays = isset($data['total_days']) ? (int) $data['total_days'] : $calendarSpanDays;

        if ($totalDays < 1 || $totalDays > $calendarSpanDays) {
            throw new DomainException("Requested leave days ({$totalDays}) must be between 1 and {$calendarSpanDays}.");
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new DomainException('A leave reason is required.');
        }

        $status = isset($data['status']) && $data['status'] instanceof LeaveRequestStatus
            ? $data['status']
            : LeaveRequestStatus::from((string) ($data['status'] ?? LeaveRequestStatus::PENDING->value));

        $attachmentPath = isset($data['attachment_path']) && trim((string) $data['attachment_path']) !== ''
            ? trim((string) $data['attachment_path'])
            : null;

        return DB::transaction(function () use (
            $employeeId,
            $data,
            $startDate,
            $endDate,
            $totalDays,
            $reason,
            $status,
            $attachmentPath,
        ): LeaveRequest {
            /** @var Employee $employee */
            $employee = $this->employeeRepository->findOrFail($employeeId);

            /** @var LeaveType $leaveType */
            $leaveType = $this->leaveTypeRepository->findOrFail($data['leave_type_id']);

            if (! $leaveType->is_active) {
                throw new DomainException("Leave type '{$leaveType->name}' is currently inactive.");
            }

            if ($leaveType->requires_attachment && $attachmentPath === null) {
                throw new DomainException("Leave type '{$leaveType->name}' requires a supporting attachment.");
            }

            $balanceYear = $startDate->year;
            $leaveBalance = $this->leaveBalanceRepository->findForEmployeeYear(
                employeeId: $employee->id,
                leaveTypeId: $leaveType->id,
                year: $balanceYear,
                lockForUpdate: true,
            );

            if ($leaveBalance === null) {
                throw new DomainException("No leave balance allocated for '{$leaveType->name}' in year {$balanceYear}.");
            }

            if ($status === LeaveRequestStatus::PENDING) {
                $hasOverlap = $this->leaveRequestRepository->hasOverlappingActiveRequest(
                    employeeId: $employee->id,
                    startDate: $startDate->toDateString(),
                    endDate: $endDate->toDateString(),
                );

                if ($hasOverlap) {
                    throw new DomainException('Employee already has a pending or approved leave request overlapping the selected dates.');
                }

                $projectedUsage = $leaveBalance->used_quota + $leaveBalance->pending_quota + $totalDays;
                if ($projectedUsage > $leaveBalance->total_quota) {
                    $available = $leaveBalance->remainingQuota();
                    throw new DomainException(
                        "Insufficient leave quota. Requested {$totalDays} day(s), but only {$available} day(s) available.",
                    );
                }

                $this->leaveBalanceRepository->update($leaveBalance, [
                    'pending_quota' => $leaveBalance->pending_quota + $totalDays,
                ]);
            }

            /** @var LeaveRequest $leaveRequest */
            $leaveRequest = $this->leaveRequestRepository->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_days' => $totalDays,
                'reason' => $reason,
                'attachment_path' => $attachmentPath,
                'status' => $status->value,
            ]);

            $this->auditLogService->record(
                event: 'leave_request.submitted',
                auditable: $leaveRequest,
                oldValues: null,
                newValues: [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'total_days' => $totalDays,
                    'status' => $status->value,
                ],
            );

            return $leaveRequest->load(['employee', 'leaveType', 'approver']);
        });
    }

    /**
     * Approve a pending leave request, moving reserved days from pending_quota to used_quota.
     */
    public function approveRequest(int|string $leaveRequestId, int|string $approverId): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequestId, $approverId): LeaveRequest {
            $leaveRequest = $this->leaveRequestRepository->findWithRelations($leaveRequestId, lockForUpdate: true);
            if ($leaveRequest === null) {
                throw new DomainException('Leave request not found.');
            }

            if ($leaveRequest->status !== LeaveRequestStatus::PENDING) {
                throw new DomainException('Only pending leave requests can be approved.');
            }

            /** @var User $approver */
            $approver = User::query()->findOrFail($approverId);
            $this->assertApproverAuthorized($leaveRequest, $approver);

            $balanceYear = CarbonImmutable::instance($leaveRequest->start_date)->year;
            $leaveBalance = $this->lockLeaveBalanceOrFail($leaveRequest, $balanceYear);

            if ($leaveBalance->pending_quota < $leaveRequest->total_days) {
                throw new DomainException('Inconsistent pending leave quota detected for this request.');
            }

            if (($leaveBalance->used_quota + $leaveRequest->total_days) > $leaveBalance->total_quota) {
                throw new DomainException('Approving this request would exceed total allocated leave quota.');
            }

            $oldPending = $leaveBalance->pending_quota;
            $oldUsed = $leaveBalance->used_quota;

            $this->leaveBalanceRepository->update($leaveBalance, [
                'pending_quota' => $oldPending - $leaveRequest->total_days,
                'used_quota' => $oldUsed + $leaveRequest->total_days,
            ]);

            $approvedAt = CarbonImmutable::now();

            /** @var LeaveRequest $updated */
            $updated = $this->leaveRequestRepository->update($leaveRequest, [
                'status' => LeaveRequestStatus::APPROVED->value,
                'approver_id' => $approver->id,
                'approved_at' => $approvedAt,
                'rejection_reason' => null,
            ]);

            $this->auditLogService->record(
                event: 'leave_request.approved',
                auditable: $updated,
                oldValues: [
                    'status' => LeaveRequestStatus::PENDING->value,
                    'pending_quota' => $oldPending,
                    'used_quota' => $oldUsed,
                ],
                newValues: [
                    'status' => LeaveRequestStatus::APPROVED->value,
                    'approver_id' => $approver->id,
                    'approved_at' => $approvedAt->toIso8601String(),
                    'pending_quota' => $oldPending - $leaveRequest->total_days,
                    'used_quota' => $oldUsed + $leaveRequest->total_days,
                ],
                actorId: $approver->id,
            );

            return $updated->load(['employee', 'leaveType', 'approver']);
        });
    }

    /**
     * Reject a pending leave request and release its reserved pending_quota back to available balance.
     */
    public function rejectRequest(int|string $leaveRequestId, int|string $approverId, string $rejectionReason): LeaveRequest
    {
        $normalizedReason = trim($rejectionReason);
        if ($normalizedReason === '') {
            throw new DomainException('A rejection reason must be provided when rejecting a leave request.');
        }

        return DB::transaction(function () use ($leaveRequestId, $approverId, $normalizedReason): LeaveRequest {
            $leaveRequest = $this->leaveRequestRepository->findWithRelations($leaveRequestId, lockForUpdate: true);
            if ($leaveRequest === null) {
                throw new DomainException('Leave request not found.');
            }

            if ($leaveRequest->status !== LeaveRequestStatus::PENDING) {
                throw new DomainException('Only pending leave requests can be rejected.');
            }

            /** @var User $approver */
            $approver = User::query()->findOrFail($approverId);
            $this->assertApproverAuthorized($leaveRequest, $approver);

            $balanceYear = CarbonImmutable::instance($leaveRequest->start_date)->year;
            $leaveBalance = $this->lockLeaveBalanceOrFail($leaveRequest, $balanceYear);

            $oldPending = $leaveBalance->pending_quota;
            $restoredPending = max(0, $oldPending - $leaveRequest->total_days);

            $this->leaveBalanceRepository->update($leaveBalance, [
                'pending_quota' => $restoredPending,
            ]);

            /** @var LeaveRequest $updated */
            $updated = $this->leaveRequestRepository->update($leaveRequest, [
                'status' => LeaveRequestStatus::REJECTED->value,
                'approver_id' => $approver->id,
                'approved_at' => null,
                'rejection_reason' => $normalizedReason,
            ]);

            $this->auditLogService->record(
                event: 'leave_request.rejected',
                auditable: $updated,
                oldValues: [
                    'status' => LeaveRequestStatus::PENDING->value,
                    'pending_quota' => $oldPending,
                ],
                newValues: [
                    'status' => LeaveRequestStatus::REJECTED->value,
                    'approver_id' => $approver->id,
                    'rejection_reason' => $normalizedReason,
                    'pending_quota' => $restoredPending,
                ],
                actorId: $approver->id,
            );

            return $updated->load(['employee', 'leaveType', 'approver']);
        });
    }

    private function assertApproverAuthorized(LeaveRequest $leaveRequest, User $approver): void
    {
        $employee = $leaveRequest->employee;
        if ($employee === null) {
            throw new DomainException('Leave request is missing its associated employee profile.');
        }

        if ($employee->user_id !== null && (int) $employee->user_id === (int) $approver->id) {
            throw new DomainException('Employees cannot approve or reject their own leave request.');
        }

        if ($approver->hasRole(RoleName::SUPERADMIN->value)) {
            return;
        }

        if (! $approver->can(PermissionName::LEAVE_APPROVE->value)) {
            throw new DomainException('User does not have permission to approve or reject leave requests.');
        }

        $isHrAdmin = $approver->hasRole(RoleName::HR_ADMIN->value);
        $isDirectManager = $employee->manager !== null
            && $employee->manager->user_id !== null
            && (int) $employee->manager->user_id === (int) $approver->id;

        if (! $isHrAdmin && ! $isDirectManager) {
            throw new DomainException('Only the employee\'s direct manager or HR Admin is authorized to action this leave request.');
        }
    }

    private function lockLeaveBalanceOrFail(LeaveRequest $leaveRequest, int $year): LeaveBalance
    {
        $leaveBalance = $this->leaveBalanceRepository->findForEmployeeYear(
            employeeId: $leaveRequest->employee_id,
            leaveTypeId: $leaveRequest->leave_type_id,
            year: $year,
            lockForUpdate: true,
        );

        if ($leaveBalance === null) {
            throw new DomainException("Leave balance record not found for year {$year}.");
        }

        return $leaveBalance;
    }
}
