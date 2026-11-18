<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Resources;

use App\Modules\Attendance\Enums\LeavePermitType;
use App\Modules\Attendance\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permitType = $this->application_type instanceof LeavePermitType
            ? $this->application_type
            : LeavePermitType::ANNUAL_LEAVE;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'application_date' => $this->application_date?->toDateString() ?? $this->created_at?->toDateString(),
            'applicant_employee_code' => $this->applicant_employee_code ?? $this->employee?->employee_code,
            'applicant_hire_date' => $this->applicant_hire_date?->toDateString() ?? $this->employee?->join_date?->toDateString(),
            'application_type' => $permitType->value,
            'application_type_label' => $permitType->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'attachment_path' => $this->attachment_path,
            'previous_leave_balance' => $this->previous_leave_balance,
            'current_leave_taken' => $this->current_leave_taken,
            'remaining_leave_balance' => $this->remaining_leave_balance,
            'status' => $this->status->value,
            'is_rejected' => (bool) $this->is_rejected,
            'approver_id' => $this->approver_id,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'hrd_other_notes' => $this->hrd_other_notes,
            'applicant_signed_at' => $this->applicant_signed_at?->toIso8601String(),
            'approved_1_by' => $this->approved_1_by,
            'approved_1_at' => $this->approved_1_at?->toIso8601String(),
            'approved_2_by' => $this->approved_2_by,
            'approved_2_at' => $this->approved_2_at?->toIso8601String(),
            'approved_3_by' => $this->approved_3_by,
            'approved_3_at' => $this->approved_3_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee?->id,
                'employee_code' => $this->employee?->employee_code,
                'full_name' => $this->employee?->full_name,
                'join_date' => $this->employee?->join_date?->toDateString(),
            ]),
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'id' => $this->leaveType?->id,
                'code' => $this->leaveType?->code,
                'name' => $this->leaveType?->name,
                'is_paid' => $this->leaveType?->is_paid,
                'requires_attachment' => $this->leaveType?->requires_attachment,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver === null ? null : [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ]),
            'approver1' => $this->whenLoaded('approver1', fn () => $this->approver1 === null ? null : [
                'id' => $this->approver1->id,
                'name' => $this->approver1->name,
                'email' => $this->approver1->email,
            ]),
            'approver2' => $this->whenLoaded('approver2', fn () => $this->approver2 === null ? null : [
                'id' => $this->approver2->id,
                'name' => $this->approver2->name,
                'email' => $this->approver2->email,
            ]),
            'approver3' => $this->whenLoaded('approver3', fn () => $this->approver3 === null ? null : [
                'id' => $this->approver3->id,
                'name' => $this->approver3->name,
                'email' => $this->approver3->email,
            ]),
        ];
    }
}
