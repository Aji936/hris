<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Enums\LeavePermitType;
use App\Modules\Attendance\Models\LeaveRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class LeavePermitPdfService
{
    /**
     * Render the official Leave & Permit Application form as a PDF binary response.
     */
    public function download(LeaveRequest $leaveRequest): Response
    {
        $leaveRequest->loadMissing([
            'employee.department',
            'employee.position',
            'leaveType',
            'approver',
            'approver1',
            'approver2',
            'approver3',
        ]);

        $pdf = Pdf::loadView('attendance.leave-permit-pdf', [
            'application' => $leaveRequest,
            'permitTypes' => LeavePermitType::cases(),
            'companyName' => config('app.name', 'PT HRIS Nusantara'),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'Leave-Permit-%s-%d.pdf',
            $leaveRequest->applicant_employee_code ?? $leaveRequest->employee?->employee_code ?? 'EMP',
            $leaveRequest->id,
        );

        return $pdf->download($filename);
    }
}
