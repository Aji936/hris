<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\User;
use App\Modules\Attendance\Contracts\LeaveBalanceRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveRequestRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveTypeRepositoryInterface;
use App\Modules\Attendance\Enums\LeavePermitType;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
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
     * Submit a Leave & Permit Application inside a transaction, snapshotting applicant identity,
     * locking annual leave quota when applicable, and recording an audit trail.
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

            [$leaveType, $permitType] = $this->resolveLeaveAndPermitType($data);

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

            $requiresBalanceQuota = in_array($permitType, [
                LeavePermitType::ANNUAL_LEAVE,
                LeavePermitType::SICK,
            ], true) || ! empty($data['leave_type_id']);

            if ($leaveBalance === null && $requiresBalanceQuota && empty($data['application_type'])) {
                throw new DomainException("No leave balance allocated for '{$leaveType->name}' in year {$balanceYear}.");
            }

            $previousBalance = null;
            $currentTaken = $totalDays;
            $remainingBalance = null;

            if ($status === LeaveRequestStatus::PENDING) {
                if (! $permitType->usesTimeWindow()) {
                    $hasOverlap = $this->leaveRequestRepository->hasOverlappingActiveRequest(
                        employeeId: $employee->id,
                        startDate: $startDate->toDateString(),
                        endDate: $endDate->toDateString(),
                    );

                    if ($hasOverlap) {
                        throw new DomainException('Employee already has a pending or approved leave request overlapping the selected dates.');
                    }
                }

                if ($leaveBalance !== null) {
                    $available = $leaveBalance->remainingQuota();
                    $projectedUsage = $leaveBalance->used_quota + $leaveBalance->pending_quota + $totalDays;
                    if ($projectedUsage > $leaveBalance->total_quota) {
                        throw new DomainException(
                            "Insufficient leave quota. Requested {$totalDays} day(s), but only {$available} day(s) available.",
                        );
                    }

                    $previousBalance = $available;
                    $remainingBalance = $available - $totalDays;

                    $this->leaveBalanceRepository->update($leaveBalance, [
                        'pending_quota' => $leaveBalance->pending_quota + $totalDays,
                    ]);
                } elseif ($permitType === LeavePermitType::ANNUAL_LEAVE) {
                    throw new DomainException("No leave balance allocated for '{$leaveType->name}' in year {$balanceYear}.");
                }
            }

            $now = CarbonImmutable::now();

            /** @var LeaveRequest $leaveRequest */
            $leaveRequest = $this->leaveRequestRepository->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'application_date' => $now->toDateString(),
                'applicant_employee_code' => $employee->employee_code,
                'applicant_hire_date' => $employee->join_date?->toDateString(),
                'application_type' => $permitType->value,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'total_days' => $totalDays,
                'reason' => $reason,
                'attachment_path' => $attachmentPath,
                'previous_leave_balance' => $previousBalance,
                'current_leave_taken' => $currentTaken,
                'remaining_leave_balance' => $remainingBalance,
                'status' => $status->value,
                'is_rejected' => false,
                'applicant_signed_at' => $now,
            ]);

            $this->auditLogService->record(
                event: 'leave_request.submitted',
                auditable: $leaveRequest,
                oldValues: null,
                newValues: [
                    'employee_id' => $employee->id,
                    'applicant_employee_code' => $employee->employee_code,
                    'leave_type_id' => $leaveType->id,
                    'application_type' => $permitType->value,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'start_time' => $leaveRequest->start_time,
                    'end_time' => $leaveRequest->end_time,
                    'total_days' => $totalDays,
                    'status' => $status->value,
                ],
            );

            return $leaveRequest->load(['employee', 'leaveType', 'approver', 'approver1', 'approver2', 'approver3']);
        });
    }

    /**
     * Approve a Leave & Permit Application (supports both single-step approval and 3-level sequential workflow:
     * Level 1 = Assistant Head of Dept, Level 2 = Dept. Manager, Level 3 = HRD with Leave Calculation).
     *
     * @param  array<string, mixed>  $hrdPayload
     */
    public function approveRequest(
        int|string $leaveRequestId,
        int|string $approverId,
        ?int $level = null,
        array $hrdPayload = [],
    ): LeaveRequest {
        if ($level !== null) {
            return $this->approveByLevel($leaveRequestId, $approverId, $level, $hrdPayload);
        }

        return DB::transaction(function () use ($leaveRequestId, $approverId): LeaveRequest {
            $leaveRequest = $this->leaveRequestRepository->findWithRelations($leaveRequestId, lockForUpdate: true);
            if ($leaveRequest === null) {
                throw new DomainException('Leave request not found.');
            }

            if (! $leaveRequest->status->isPendingReview()) {
                throw new DomainException('Only pending leave requests can be approved.');
            }

            /** @var User $approver */
            $approver = User::query()->findOrFail($approverId);
            $this->assertApproverAuthorized($leaveRequest, $approver);

            $balanceYear = CarbonImmutable::instance($leaveRequest->start_date)->year;
            $leaveBalance = $this->leaveBalanceRepository->findForEmployeeYear(
                employeeId: $leaveRequest->employee_id,
                leaveTypeId: $leaveRequest->leave_type_id,
                year: $balanceYear,
                lockForUpdate: true,
            );

            $oldPending = 0;
            $oldUsed = 0;

            if ($leaveBalance !== null) {
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
            }

            $approvedAt = CarbonImmutable::now();

            /** @var LeaveRequest $updated */
            $updated = $this->leaveRequestRepository->update($leaveRequest, [
                'status' => LeaveRequestStatus::APPROVED->value,
                'is_rejected' => false,
                'approver_id' => $approver->id,
                'approved_at' => $approvedAt,
                'approved_3_by' => $leaveRequest->approved_3_by ?? $approver->id,
                'approved_3_at' => $leaveRequest->approved_3_at ?? $approvedAt,
                'rejection_reason' => null,
            ]);

            $this->auditLogService->record(
                event: 'leave_request.approved',
                auditable: $updated,
                oldValues: [
                    'status' => $leaveRequest->status->value,
                    'pending_quota' => $oldPending,
                    'used_quota' => $oldUsed,
                ],
                newValues: [
                    'status' => LeaveRequestStatus::APPROVED->value,
                    'approver_id' => $approver->id,
                    'approved_at' => $approvedAt->toIso8601String(),
                    'pending_quota' => max(0, $oldPending - $leaveRequest->total_days),
                    'used_quota' => $oldUsed + $leaveRequest->total_days,
                ],
                actorId: $approver->id,
            );

            return $updated->load(['employee', 'leaveType', 'approver', 'approver1', 'approver2', 'approver3']);
        });
    }

    /**
     * Execute sequential 3-level approval:
     * - Level 1 (Approved I): Assistant Head of Department -> APPROVED_L1
     * - Level 2 (Approved II): Dept. Manager -> APPROVED_L2
     * - Level 3 (Approved III): HRD -> APPROVED_HRD (or REJECTED if is_rejected checkbox is ticked),
     *   finalizing Calculation of Leave and deducting quota.
     *
     * @param  array<string, mixed>  $hrdPayload
     */
    public function approveByLevel(
        int|string $leaveRequestId,
        int|string $approverId,
        int $level,
        array $hrdPayload = [],
    ): LeaveRequest {
        if (! empty($hrdPayload['is_rejected'])) {
            $reason = (string) ($hrdPayload['rejection_reason'] ?? 'Rejected by HRD');

            return $this->rejectRequest(
                leaveRequestId: $leaveRequestId,
                approverId: $approverId,
                rejectionReason: $reason,
                hrdOtherNotes: isset($hrdPayload['hrd_other_notes']) ? (string) $hrdPayload['hrd_other_notes'] : null,
            );
        }

        return DB::transaction(function () use ($leaveRequestId, $approverId, $level, $hrdPayload): LeaveRequest {
            $leaveRequest = $this->leaveRequestRepository->findWithRelations($leaveRequestId, lockForUpdate: true);
            if ($leaveRequest === null) {
                throw new DomainException('Leave request not found.');
            }

            if (! $leaveRequest->status->isPendingReview()) {
                throw new DomainException('This application has already been finalized.');
            }

            /** @var User $approver */
            $approver = User::query()->findOrFail($approverId);
            $this->assertApproverAuthorized($leaveRequest, $approver);

            $now = CarbonImmutable::now();
            $oldStatus = $leaveRequest->status->value;

            if ($level === 1) {
                /** @var LeaveRequest $updated */
                $updated = $this->leaveRequestRepository->update($leaveRequest, [
                    'status' => LeaveRequestStatus::APPROVED_L1->value,
                    'approved_1_by' => $approver->id,
                    'approved_1_at' => $now,
                ]);

                $this->auditLogService->record(
                    event: 'leave_request.approved_l1',
                    auditable: $updated,
                    oldValues: ['status' => $oldStatus],
                    newValues: [
                        'status' => LeaveRequestStatus::APPROVED_L1->value,
                        'approved_1_by' => $approver->id,
                        'approved_1_at' => $now->toIso8601String(),
                    ],
                    actorId: $approver->id,
                );

                return $updated->load(['employee', 'leaveType', 'approver', 'approver1', 'approver2', 'approver3']);
            }

            if ($level === 2) {
                /** @var LeaveRequest $updated */
                $updated = $this->leaveRequestRepository->update($leaveRequest, [
                    'status' => LeaveRequestStatus::APPROVED_L2->value,
                    'approved_2_by' => $approver->id,
                    'approved_2_at' => $now,
                ]);

                $this->auditLogService->record(
                    event: 'leave_request.approved_l2',
                    auditable: $updated,
                    oldValues: ['status' => $oldStatus],
                    newValues: [
                        'status' => LeaveRequestStatus::APPROVED_L2->value,
                        'approved_2_by' => $approver->id,
                        'approved_2_at' => $now->toIso8601String(),
                    ],
                    actorId: $approver->id,
                );

                return $updated->load(['employee', 'leaveType', 'approver', 'approver1', 'approver2', 'approver3']);
            }

            // Level 3: HRD Final Approval & Leave Balance Calculation
            if (! $approver->hasRole([RoleName::HR_ADMIN->value, RoleName::SUPERADMIN->value])) {
                throw new DomainException('Only HRD (HR Admin / Superadmin) can finalize Level 3 approval and leave calculation.');
            }

            $balanceYear = CarbonImmutable::instance($leaveRequest->start_date)->year;
            $leaveBalance = $this->leaveBalanceRepository->findForEmployeeYear(
                employeeId: $leaveRequest->employee_id,
                leaveTypeId: $leaveRequest->leave_type_id,
                year: $balanceYear,
                lockForUpdate: true,
            );

            $previousBalance = isset($hrdPayload['previous_leave_balance'])
                ? (int) $hrdPayload['previous_leave_balance']
                : ($leaveRequest->previous_leave_balance ?? ($leaveBalance !== null ? $leaveBalance->total_quota - $leaveBalance->used_quota : 0));

            $currentTaken = isset($hrdPayload['current_leave_taken'])
                ? (int) $hrdPayload['current_leave_taken']
                : ($leaveRequest->current_leave_taken ?? $leaveRequest->total_days);

            $remainingBalance = isset($hrdPayload['remaining_leave_balance'])
                ? (int) $hrdPayload['remaining_leave_balance']
                : ($previousBalance - $currentTaken);

            if ($leaveBalance !== null && $leaveBalance->pending_quota >= $leaveRequest->total_days) {
                $this->leaveBalanceRepository->update($leaveBalance, [
                    'pending_quota' => max(0, $leaveBalance->pending_quota - $leaveRequest->total_days),
                    'used_quota' => $leaveBalance->used_quota + $currentTaken,
                ]);
            }

            /** @var LeaveRequest $updated */
            $updated = $this->leaveRequestRepository->update($leaveRequest, [
                'status' => LeaveRequestStatus::APPROVED_HRD->value,
                'is_rejected' => false,
                'previous_leave_balance' => $previousBalance,
                'current_leave_taken' => $currentTaken,
                'remaining_leave_balance' => $remainingBalance,
                'hrd_other_notes' => $hrdPayload['hrd_other_notes'] ?? $leaveRequest->hrd_other_notes,
                'approver_id' => $approver->id,
                'approved_at' => $now,
                'approved_3_by' => $approver->id,
                'approved_3_at' => $now,
                'rejection_reason' => null,
            ]);

            $this->auditLogService->record(
                event: 'leave_request.approved_hrd',
                auditable: $updated,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => LeaveRequestStatus::APPROVED_HRD->value,
                    'approved_3_by' => $approver->id,
                    'approved_3_at' => $now->toIso8601String(),
                    'previous_leave_balance' => $previousBalance,
                    'current_leave_taken' => $currentTaken,
                    'remaining_leave_balance' => $remainingBalance,
                    'hrd_other_notes' => $updated->hrd_other_notes,
                ],
                actorId: $approver->id,
            );

            return $updated->load(['employee', 'leaveType', 'approver', 'approver1', 'approver2', 'approver3']);
        });
    }

    /**
     * Reject a pending leave request and release its reserved pending_quota back to available balance.
     */
    public function rejectRequest(
        int|string $leaveRequestId,
        int|string $approverId,
        string $rejectionReason,
        ?string $hrdOtherNotes = null,
    ): LeaveRequest {
        $normalizedReason = trim($rejectionReason);
        if ($normalizedReason === '') {
            throw new DomainException('A rejection reason must be provided when rejecting a leave request.');
        }

        return DB::transaction(function () use ($leaveRequestId, $approverId, $normalizedReason, $hrdOtherNotes): LeaveRequest {
            $leaveRequest = $this->leaveRequestRepository->findWithRelations($leaveRequestId, lockForUpdate: true);
            if ($leaveRequest === null) {
                throw new DomainException('Leave request not found.');
            }

            if (! $leaveRequest->status->isPendingReview()) {
                throw new DomainException('Only pending leave requests can be rejected.');
            }

            /** @var User $approver */
            $approver = User::query()->findOrFail($approverId);
            $this->assertApproverAuthorized($leaveRequest, $approver);

            $balanceYear = CarbonImmutable::instance($leaveRequest->start_date)->year;
            $leaveBalance = $this->leaveBalanceRepository->findForEmployeeYear(
                employeeId: $leaveRequest->employee_id,
                leaveTypeId: $leaveRequest->leave_type_id,
                year: $balanceYear,
                lockForUpdate: true,
            );

            $oldPending = 0;
            $restoredPending = 0;

            if ($leaveBalance !== null) {
                $oldPending = $leaveBalance->pending_quota;
                $restoredPending = max(0, $oldPending - $leaveRequest->total_days);

                $this->leaveBalanceRepository->update($leaveBalance, [
                    'pending_quota' => $restoredPending,
                ]);
            }

            /** @var LeaveRequest $updated */
            $updated = $this->leaveRequestRepository->update($leaveRequest, [
                'status' => LeaveRequestStatus::REJECTED->value,
                'is_rejected' => true,
                'approver_id' => $approver->id,
                'approved_at' => null,
                'rejection_reason' => $normalizedReason,
                'hrd_other_notes' => $hrdOtherNotes ?? $leaveRequest->hrd_other_notes,
            ]);

            $this->auditLogService->record(
                event: 'leave_request.rejected',
                auditable: $updated,
                oldValues: [
                    'status' => $leaveRequest->status->value,
                    'pending_quota' => $oldPending,
                ],
                newValues: [
                    'status' => LeaveRequestStatus::REJECTED->value,
                    'is_rejected' => true,
                    'approver_id' => $approver->id,
                    'rejection_reason' => $normalizedReason,
                    'hrd_other_notes' => $updated->hrd_other_notes,
                    'pending_quota' => $restoredPending,
                ],
                actorId: $approver->id,
            );

            return $updated->load(['employee', 'leaveType', 'approver', 'approver1', 'approver2', 'approver3']);
        });
    }

    /**
     * Update a master LeaveType definition inside a transaction and record an audit log entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateLeaveType(LeaveType $leaveType, array $data): LeaveType
    {
        return DB::transaction(function () use ($leaveType, $data): LeaveType {
            $oldValues = [
                'code' => $leaveType->code,
                'name' => $leaveType->name,
                'default_quota_days' => $leaveType->default_quota_days,
                'is_paid' => $leaveType->is_paid,
                'requires_attachment' => $leaveType->requires_attachment,
                'is_active' => $leaveType->is_active,
            ];

            if (isset($data['code'])) {
                $data['code'] = strtoupper(trim((string) $data['code']));
            }

            /** @var LeaveType $updated */
            $updated = $this->leaveTypeRepository->update($leaveType, $data);

            $this->auditLogService->record(
                event: 'leave_type.updated',
                auditable: $updated,
                oldValues: $oldValues,
                newValues: [
                    'code' => $updated->code,
                    'name' => $updated->name,
                    'default_quota_days' => $updated->default_quota_days,
                    'is_paid' => $updated->is_paid,
                    'requires_attachment' => $updated->requires_attachment,
                    'is_active' => $updated->is_active,
                ],
            );

            return $updated;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: LeaveType, 1: LeavePermitType}
     */
    private function resolveLeaveAndPermitType(array $data): array
    {
        $requestedPermit = null;
        if (! empty($data['application_type'])) {
            $requestedPermit = $data['application_type'] instanceof LeavePermitType
                ? $data['application_type']
                : LeavePermitType::tryFrom((string) $data['application_type']);
        }

        if (! empty($data['leave_type_id'])) {
            /** @var LeaveType $leaveType */
            $leaveType = $this->leaveTypeRepository->findOrFail($data['leave_type_id']);
            $permitType = $requestedPermit ?? match (strtoupper($leaveType->code)) {
                'SICK' => LeavePermitType::SICK,
                'ANNUAL', 'ANNUAL_LEAVE' => LeavePermitType::ANNUAL_LEAVE,
                'LATE_COMING' => LeavePermitType::LATE_COMING,
                'EARLY_LEAVING' => LeavePermitType::EARLY_LEAVING,
                'NO_ABSENT_RECORD' => LeavePermitType::NO_ABSENT_RECORD,
                default => LeavePermitType::SPECIAL_PERMISSION,
            };

            return [$leaveType, $permitType];
        }

        $permitType = $requestedPermit ?? LeavePermitType::ANNUAL_LEAVE;

        $preferredCode = match ($permitType) {
            LeavePermitType::SICK => 'SICK',
            LeavePermitType::ANNUAL_LEAVE => 'ANNUAL',
            default => $permitType->value,
        };

        $existingType = LeaveType::query()
            ->whereIn('code', [$preferredCode, $permitType->value])
            ->first();

        if ($existingType === null) {
            $existingType = LeaveType::query()->create([
                'code' => $permitType->value,
                'name' => $permitType->label(),
                'default_quota_days' => $permitType === LeavePermitType::ANNUAL_LEAVE ? 12 : 14,
                'is_paid' => true,
                'requires_attachment' => false,
                'is_active' => true,
            ]);
        }

        return [$existingType, $permitType];
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
}
