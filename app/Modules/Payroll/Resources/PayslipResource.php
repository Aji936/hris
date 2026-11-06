<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Modules\Payroll\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payslip
 */
class PayslipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_id' => $this->payroll_id,
            'employee_id' => $this->employee_id,
            'payslip_number' => $this->payslip_number,
            'employee_snapshot' => $this->employee_snapshot,
            'basic_salary' => (string) $this->basic_salary,
            'total_allowance' => (string) $this->total_allowance,
            'total_deduction' => (string) $this->total_deduction,
            'net_salary' => (string) $this->net_salary,
            'attendance_summary' => $this->attendance_summary,
            'status' => $this->status->value,
            'payroll' => $this->whenLoaded('payroll', fn () => [
                'id' => $this->payroll?->id,
                'payroll_period' => $this->payroll?->payroll_period,
                'title' => $this->payroll?->title,
                'status' => $this->payroll?->status->value,
            ]),
            'items' => PayslipItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
