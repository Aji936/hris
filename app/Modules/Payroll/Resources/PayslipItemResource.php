<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Resources;

use App\Modules\Payroll\Models\PayslipItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayslipItem
 */
class PayslipItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payslip_id' => $this->payslip_id,
            'payroll_component_id' => $this->payroll_component_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'amount' => (string) $this->amount,
        ];
    }
}
