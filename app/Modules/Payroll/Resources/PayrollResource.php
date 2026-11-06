<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Modules\Payroll\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payroll
 */
class PayrollResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period' => $this->payroll_period,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'title' => $this->title,
            'status' => $this->status->value,
            'total_gross_amount' => (string) $this->total_gross_amount,
            'total_deduction_amount' => (string) $this->total_deduction_amount,
            'total_net_amount' => (string) $this->total_net_amount,
            'generated_by' => $this->generated_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'payslips_count' => $this->whenCounted('payslips'),
            'payslips' => PayslipResource::collection($this->whenLoaded('payslips')),
        ];
    }
}
